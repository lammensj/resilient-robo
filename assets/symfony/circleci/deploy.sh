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

  mkdir -p ${PROJECT_ROOT}/shared/backup/${NEXT}
  cp ${PROJECT_ROOT}/shared/${BRANCH}.env ${PROJECT_ROOT}/shared/backup/${NEXT}/${BRANCH}.env
  cp ${PROJECT_ROOT}/shared/${BRANCH}.env .env

  runner_log_notice 'Copying credential files... DONE'

  SF_ROOT=$(pwd)
  CONSOLE='php ./bin/console'

  runner_sequence config_include_files config_backup || return
}

#######################################
# Configuration: includes public and private files.
#######################################
task_config_include_files() {
  runner_log_notice 'Including public and private files...'
  ln -sfn ${PROJECT_ROOT}/shared/uploads ./public/uploads
  runner_log_success 'Including public and private files... DONE'
}

#######################################
# Configuration: takes a backup of the database.
#######################################
task_config_backup() {
  runner_log_notice 'Backing up database...'

  DATABASE_URL=$(grep DATABASE_URL ${PROJECT_ROOT}/shared/${BRANCH}.env | cut -d '=' -f2)
  PROTO="$(echo ${DATABASE_URL} | grep :// | sed -e's,^\(.*://\).*,\1,g')"
  DB_URL="$(echo ${1/${PROTO}/})"
  DB_USER="$(echo ${DB_URL} | grep @ | cut -d@ -f1)"
  DB_PASS="$(echo ${DB_USER} | grep : | cut -d: -f2)"
  DB_HOST="$(echo ${DB_URL/${USER}:${DB_PASS}@/} | cut -d/ -f1)"
  DB_PORT="$(echo ${DB_HOST} | sed -e 's,^.*:,:,g' -e 's,.*:\([0-9]*\).*,\1,g' -e 's,[^0-9],,g')"
  DB_HOST="$(echo ${DB_HOST/:${PORT}})"
  DB_NAME="$(echo ${DB_URL} | grep / | cut -d/ -f2-)"

  mysqldump -u ${DB_USER} -p ${DB_PASS} -h ${DB_HOST} -P ${DB_PORT} ${DB_NAME} | gzip > ${PROJECT_ROOT}/shared/backup/${NEXT}/backup.sql.zip

  tar -zcf ${PROJECT_ROOT}/shared/backup/${NEXT}.tar.gz -C ${PROJECT_ROOT}/shared/backup ${NEXT}
  rm -rf ${PROJECT_ROOT}/shared/backup/${NEXT}
  runner_log_success 'Backing up database... DONE'
}


#######################################
# Symlink: re-routes symlinks.
#######################################
task_symlink() {
  runner_log_notice 'Rerouting next to current...'
  cd ${PROJECT_ROOT}
  ln -sfn ${NEXT} current
  ln -sfn current/public www
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

  mysql -u ${DB_USER} -p ${DB_PASS} -h ${DB_HOST} -P ${DB_PORT} ${DB_NAME} < ${PROJECT_ROOT}/shared/backup/rollback/backup.sql

  rm -rf ${PROJECT_ROOT}/shared/backup/rollback
  runner_log_success 'Rolling back... DONE'
  exit
}
