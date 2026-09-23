#!/usr/bin/env bash
#
# One command for the whole suite, cluster and all.
#
#   scripts/test.sh                                 unit, database and integration
#   scripts/test.sh --testsuite database            anything else phpunit understands
#   scripts/test.sh --coverage-text                 with coverage
#
# A throwaway cluster is started before phpunit and removed afterwards - **unless one was
# already up**, in which case it is left alone. Running `scripts/test-cluster.sh up` once
# therefore saves the four seconds of boot on every run after it, and nothing else changes.
#
set -euo pipefail

cd "$(dirname "$0")/.."
# shellcheck source=scripts/test-cluster.sh
source scripts/test-cluster.sh

started_it=0
if cluster_up; then
    started_it=1
fi
# Also on ctrl-c: an interrupted run is exactly when a cluster gets forgotten.
trap '[ "$started_it" = "1" ] && cluster_down' EXIT INT TERM

# PCOV is switched off in the ini so an ordinary request pays nothing for it; a coverage
# run turns it on for itself. Asking for coverage without it produces an empty report and
# a warning, rather than a failure, so it is worth doing here and not in the caller.
#
# The report needs more memory than the tests: the HTML one holds every test's lines for a
# file while it renders it, and ran out at 128 MB with 2296 tests. Only here, so an ordinary
# run still fails on a test that eats memory.
php_flags=''
case "$*" in
    *--coverage*) php_flags='-d pcov.enabled=1 -d memory_limit=1G' ;;
esac

# KUBERNETES_TEST_CLUSTER=disposable is what unlocks the tests that write to a cluster.
# Without it ClusterTestCase skips, which is what protects every cluster that is not this
# one. DatabaseTestCase clears the cluster environment for everything outside the
# integration suite, so the three suites can run in a single process.
docker exec \
    -e KUBERNETES_AUTH=kube-config \
    -e KUBERNETES_TEST_CLUSTER=disposable \
    -e KUBERNETES_KUBECONFIG="$(cluster_kubeconfig)" \
    "$APP" sh -c "cd ci4 && php $php_flags vendor/bin/phpunit --colors=always ${*:---testsuite unit,database,integration}"
