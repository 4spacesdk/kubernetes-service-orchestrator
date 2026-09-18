#!/usr/bin/env bash
#
# A Kubernetes cluster that exists for the length of a test run.
#
# The integration suite writes: it creates namespaces, applies deployments and deletes them
# again. Nothing that does that belongs anywhere near a cluster somebody uses, so this
# starts a k3s server in a container and throws it away afterwards.
#
# k3s rather than kind or minikube because it needs nothing but Docker - no binary on the
# developer's machine and none on the build machine either. It is ready in four seconds.
#
#   scripts/test-cluster.sh up      start one and leave it up
#   scripts/test-cluster.sh down    remove it
#
# `scripts/test.sh` sources this file and starts one on its own, so neither command is
# needed for an ordinary test run. Keeping one up is worth it when running the suite over
# and over: test.sh only removes a cluster it started itself.
#
set -euo pipefail

CLUSTER=kso-test-cluster
APP=4s-deploy-service-app
IMAGE=rancher/k3s:v1.31.5-k3s1

cluster_network() {
    docker inspect "$APP" --format '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{end}}'
}

cluster_is_running() {
    [ "$(docker inspect -f '{{.State.Running}}' "$CLUSTER" 2>/dev/null || true)" = "true" ]
}

# Returns 0 if it started one, 1 if there already was one. The caller uses that to decide
# whether taking it down again is its business.
cluster_up() {
    if cluster_is_running; then
        return 1
    fi
    docker rm -f "$CLUSTER" >/dev/null 2>&1 || true

    # On the app's network, and with the container name in the certificate, so the app can
    # reach the api server under the name the kubeconfig will carry.
    docker run -d --name "$CLUSTER" --privileged --network "$(cluster_network)" \
        -e K3S_KUBECONFIG_MODE=666 \
        "$IMAGE" server \
        --disable=traefik --disable=metrics-server --disable=servicelb \
        --tls-san="$CLUSTER" >/dev/null

    printf 'Starting %s' "$CLUSTER" >&2
    for _ in $(seq 60); do
        if docker exec "$CLUSTER" kubectl get --raw /readyz >/dev/null 2>&1; then
            printf ' - ready, installing definitions' >&2
            cluster_install_crds
            printf ' - done.\n' >&2

            return 0
        fi
        printf '.' >&2
        sleep 1
    done

    printf '\n' >&2
    echo "The cluster never became ready. Log:" >&2
    docker logs --tail 40 "$CLUSTER" >&2
    exit 1
}

# Six of kso's deployment steps build resources Kubernetes has never heard of. Without the
# definitions the api server rejects them as unknown kinds, and the tests would be testing
# the rejection. The urls are in scripts/cluster-crds.txt, which the build reads too.
cluster_install_crds() {
    while read -r url; do
        case "$url" in ''|'#'*) continue ;; esac
        if ! docker exec "$CLUSTER" kubectl apply --server-side -f "$url" >/dev/null; then
            echo "Could not install $url - the cluster is up but six steps cannot be tested." >&2
            exit 1
        fi
    done < "$(dirname "${BASH_SOURCE[0]}")/cluster-crds.txt"
}

cluster_down() {
    docker rm -f "$CLUSTER" >/dev/null 2>&1 || true
}

# The kubeconfig k3s writes points at 127.0.0.1, which from inside the app container is the
# app container. The api server answers under its own name instead.
cluster_kubeconfig() {
    docker exec "$CLUSTER" sed "s#127.0.0.1#$CLUSTER#" /etc/rancher/k3s/k3s.yaml | base64 | tr -d '\n'
}

# Sourced by test.sh, which wants the functions and not the commands.
if [ "${BASH_SOURCE[0]}" = "$0" ]; then
    case "${1:-up}" in
        up) cluster_up || echo "$CLUSTER was already up." ;;
        down) cluster_down; echo "$CLUSTER is gone." ;;
        *) echo "usage: $0 {up|down}" >&2; exit 64 ;;
    esac
fi
