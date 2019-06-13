#!/usr/bin/env bash
cd "$(dirname "$0")" || exit
[ -f runner.sh ] || wget https://raw.githubusercontent.com/stylemistake/runner/master/src/runner.sh

CIRCLECI_TOKEN='abc123'
BRANCH='testing'
PROJECT_ROOT=$(pwd)

source ./runner.sh

#######################################
# Default task to run.
#######################################
task_default() {
  while [ "$#" -gt 0 ]; do
    case "$1" in
      --token=*)
        CIRCLECI_TOKEN="${1#*=}"
        ;;
      --branch=*)
        BRANCH="${1#*=}"
        ;;
    esac
    shift
  done

  runner_sequence artifact config symlink cleanup
}

#######################################
# Artifact: downloads the artifact.
#######################################
task_artifact() {
  runner_log_notice 'Retrieving latest build artifact...'

  CT_PARAM="?circle-token=${CIRCLECI_TOKEN}"
  ARTIFACT=$(curl -s "https://circleci.com/api/v1.1/project/github/[repo_id]/latest/artifacts${CT_PARAM}&filter=successful&branch=${BRANCH}" | grep -o 'https://[^"]*')
  wget -q ${ARTIFACT}${CT_PARAM} -O archive.tar.gz

  runner_log_success 'Retrieving latest build artifact... DONE'

  runner_sequence artifact_build_dir artifact_decompress_archive || return
}

#######################################
# Artifact: builds a new destination directory.
#######################################
task_artifact_build_dir() {
  runner_log_notice 'Creating new destination directory...'
  NEXT="build-$(date +%Y%m%d-%H%M%S)"
  mkdir ${NEXT}
  ln -fns ${NEXT} next
  runner_log_success 'Creating new destination directory... DONE'
}

#######################################
# Artifact: unpacks the compressed artifact.
#######################################
task_artifact_decompress_archive() {
  runner_log_notice 'Decompressing archive...'
  tar --no-same-owner -xzf archive.tar.gz -C ${NEXT}
  rm -f archive.tar.gz
  runner_log_success 'Decompressing archive... DONE'
}

#######################################
# Configuration: prepares the credential files.
#######################################
task_config() {
  runner_log_notice 'Copying credential files...'

  cd ./next

  cp ./app/sites/default/default.services.yml ./app/sites/default/services.yml
  cp ./app/sites/default/default.settings.php ./app/sites/default/settings.php
  mkdir -p ${PROJECT_ROOT}/shared/backup/${NEXT}
  cp ${PROJECT_ROOT}/shared/${BRANCH}.settings.private.php ${PROJECT_ROOT}/shared/backup/${NEXT}/${BRANCH}.settings.private.php
  cp ${PROJECT_ROOT}/shared/${BRANCH}.settings.private.php ./app/sites/default/settings.private.php
  cp ${PROJECT_ROOT}/shared/${BRANCH}.salt.txt ${PROJECT_ROOT}/shared/backup/${NEXT}/${BRANCH}.salt.txt
  cp ${PROJECT_ROOT}/shared/${BRANCH}.salt.txt salt.txt

  INCL_PRVT_SETTINGS='include $app_root . "/" . $site_path . "/settings.private.php";'
  SETTINGS_FILE=./app/sites/default/settings.php
  grep -qF "${INCL_PRVT_SETTINGS}" ${SETTINGS_FILE} || echo "${INCL_PRVT_SETTINGS}" >> ${SETTINGS_FILE}

  runner_log_notice 'Copying credential files... DONE'

  DRUPAL_ROOT=$(pwd)/app
  DRUSH=./vendor/bin/drush

  runner_sequence config_include_files config_backup config_install config_update
}

#######################################
# Configuration: includes public and private files.
#######################################
task_config_include_files() {
  runner_log_notice 'Including public and private files...'
  rm -Rf ./app/sites/default/files
  ln -sfn ${PROJECT_ROOT}/shared/files ./app/sites/default/files
  if [ -f ${PROJECT_ROOT}/shared/private_file_systen ]; then
    ln -sfn ${PROJECT_ROOT}/shared/private_file_systen ../private_file_systen
  fi
  runner_log_success 'Including public and private files... DONE'
}

#######################################
# Configuration: takes a backup of the database.
#######################################
task_config_backup() {
  runner_log_notice 'Backing up database...'
  ${DRUSH} sql-dump --result-file=${PROJECT_ROOT}/shared/backup/${NEXT}/backup.sql --gzip -r ${DRUPAL_ROOT}
  tar -zcf ${PROJECT_ROOT}/shared/backup/${NEXT}.tar.gz -C ${PROJECT_ROOT}/shared/backup ${NEXT}
  rm -rf ${PROJECT_ROOT}/shared/backup/${NEXT}
  runner_log_success 'Backing up database... DONE'
}

#######################################
# Configuration: installs a new site.
#######################################
task_config_install() {
  if [ ! -s ${PROJECT_ROOT}/shared/${BRANCH}.salt.txt ]; then
    runner_log_notice 'Installing new site...'
    ${DRUSH} si resilient --existing-config -y -r ${DRUPAL_ROOT}
    runner_log_success 'Installing new site... DONE'

    runner_log_notice 'Preserving hash salt...'
    grep -o -E '([A-Za-z0-9_-]{74})' ./app/sites/default/settings.php >> ${PROJECT_ROOT}/shared/${BRANCH}.salt.txt
    grep -o -E '([A-Za-z0-9_-]{74})' ./app/sites/default/settings.php >> salt.txt
    runner_log_success 'Preserving hash salt... DONE'
  else
    runner_log_notice 'Site already installed... SKIPPING'
  fi
}

#######################################
# Configuration: updates configuration.
#######################################
task_config_update() {
  runner_log_notice 'Updating configuration...'
  ${DRUSH} updb -y -r ${DRUPAL_ROOT} || runner_sequence rollback
  ${DRUSH} cc drush -r ${DRUPAL_ROOT} || runner_sequence rollback
  ${DRUSH} csim -y -r ${DRUPAL_ROOT} || ${DRUSH} cim -y -r ${DRUPAL_ROOT} || runner_sequence rollback
  ${DRUSH} entup -y -r ${DRUPAL_ROOT} || runner_sequence rollback
  runner_log_success 'Updating configuration... DONE'
}


#######################################
# Symlink: re-routes symlinks.
#######################################
task_symlink() {
  runner_log_notice 'Rerouting next to current...'
  cd ${PROJECT_ROOT}
  ln -sfn ${NEXT} current
  ln -sfn current/web www
  rm next
  runner_log_success 'Rerouting next to current... DONE'
}


#######################################
# Cleanup: remove leftovers.
#######################################
task_cleanup() {
  runner_log_notice 'Removing older builds...'
  ls -d ${PROJECT_ROOT}/build-* | head -n -3 | xargs -r chmod -R 777
  ls -d ${PROJECT_ROOT}/build-* | head -n -3 | xargs -r rm -Rf
  ls -d ${PROJECT_ROOT}/shared/backup/build-* | head -n -3 | xargs -r rm -Rf
  runner_log_success 'Removing older builds... DONE'
}


#######################################
# Rollback: imports the backed-up database.
#######################################
task_rollback() {
  runner_log_notice 'Rolling back...'
  mkdir -p ${PROJECT_ROOT}/shared/backup/rollback
  ls -t ${PROJECT_ROOT}/shared/backup/build-*.tar.gz -t | head -1 | xargs tar -xC ${PROJECT_ROOT}/shared/backup/rollback --strip-components=1 -f
  [ -f ${PROJECT_ROOT}/shared/backup/rollback/backup.sql.gz ] && gunzip ${PROJECT_ROOT}/shared/backup/rollback/backup.sql.gz || return
  ${DRUSH} sql-cli < ${PROJECT_ROOT}/shared/backup/rollback/backup.sql
  rm -rf ${PROJECT_ROOT}/shared/backup/rollback
  runner_log_success 'Rolling back... DONE'
  exit
}
