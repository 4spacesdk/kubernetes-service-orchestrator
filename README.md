# Kubernetes Service Orchestrator
The Kubernetes Service Orchestrator (KSO) is a powerful service deployment solution. 
This service enables interaction with the Kubernetes API and facilitates the effortless deployment of other services within a Kubernetes cluster.

## Features
* Automated deployment of services in Kubernetes clusters.
* User-friendly Vue-based interface for easy configuration and monitoring.
* Handling of Kubernetes API calls and resource management.
* Option for custom configurations and adaptations.
* Nightly scan of the images your deployments run for known vulnerabilities, with Trivy.

## Security status
Known vulnerabilities in the main branch - its dependencies and the `:dev` image built from it -
are listed in [SECURITY-STATUS.md](SECURITY-STATUS.md), updated every week.

## Install kso
### Create `values.yaml` file
For a complete set of options see [link](https://github.com/4spacesdk/helm-charts/blob/master/charts/kubernetes-service-orchestrator/values.yaml)
```
# Select a cloud provider. This will enable provider-specific resources.
# "gke" also grants kso RBAC to manage HealthCheckPolicies (networking.gke.io) for the
# services it orchestrates, so non-HTTP ports such as websockets stay healthy behind a GKE Gateway.
provider: ""          # "gke" | "aks" | "eks" | ""

deployment:

  # Valid values: development, production
  environment: ""

  # You need to provide a MySQL database
  database:
    host: ""
    port: "3306"
    name: ""
    user: ""
    pass: ""

  # Required. What the credentials kso stores for other systems - registry passwords,
  # database passwords, webhook tokens - are encrypted with, along with the refresh-token
  # cookie. Any 32 characters will do, but it has to be this installation's own and it has
  # to stay the same: change it and the stored credentials cannot be read back.
  # Generate one on Linux or macOS with:  openssl rand -base64 24
  encryptionKey: ""

  # Old keys, comma separated. Set this when rotating encryptionKey, so what the old one
  # encrypted can still be read until `php spark app:reencrypt` has written it again.
  previousEncryptionKeys: ""

  # kso scans the images your deployments run for known vulnerabilities, every night, with
  # Trivy. Its databases are kept on a volume of this size between scans: 1.3 GB, plus 1.4 GB
  # once an image with Java in it has been scanned. They are fetched again when the pod starts.
  imageScanning:
    cacheSizeLimit: 4Gi

  # The default url is "https://kubernetes.default.svc.cluster.local".
  # But it can be different depending on provider
  kubernetes:
    remoteClusterUrl: "https://kubernetes.default.svc.cluster.local"

  config:
    defaults:
      imagePullSecretName: ""
      certManagerIssuerName: ""
      
    # Enable email features. Used for "forgot password". This is optional.  
#    email:
#      host: ""
#      port: ""
#      user: ""
#      pass: ""
#      sender: ""

  env: [ ]
#  env:
#    - name: ""
#      value: ""

ingress:
  enabled: false
  className: ""
  annotations: { }
  # kubernetes.io/ingress.class: nginx
  # kubernetes.io/tls-acme: "true"
  hosts:
    - host: chart-example.local
      paths:
        - path: /
          pathType: Prefix
  tls: [ ]
  #  - secretName: chart-example-tls
  #    hosts:
  #      - chart-example.local

gatewayapi:
  enabled: false
  annotations: {}
  labels: {}
  parentRefs: []
#    - name: gateway-external
#      namespace: gateway-system
  hosts:
    - chart-example.local
  healthCheckPolicy:
    enabled: true

# About 90Mi idle and 110-180Mi more while Trivy scans an image; the limit leaves room for
# larger images and the first scan after a start. No CPU limit: it only makes a scan slower.
resources:
  requests:
    cpu: 100m
    memory: 256Mi
  limits:
    memory: 1Gi

```
### Install
```
helm repo add 4spacesdk https://4spacesdk.github.io/helm-charts
helm upgrade --install kso 4spacesdk/kso --values=values.yaml --namespace kso --create-namespace
```

### Upgrade
```
helm repo update
helm upgrade --install kso 4spacesdk/kso --values=values.yaml --namespace kso
```

### Delete kso
```
helm delete kso
```

### Migrate database (Helm)
Deploying with helm will automatically start a migration job.
A init container is added to wait for the migration to finish.

### Migrate database (Manually)
1. Run database migration from inside a pod: `cd /var/www/html/ci4 && php spark migrate`
2. If you have multiple pods running this application, you need to clear ORM cache in every pod
    1. `cd /var/www/html/ci4 && php spark orm:clear:cache` 

## If a secret leaks
* **`encryptionKey`** - what the credentials kso stores are encrypted with:
  1. Put a new key in `deployment.encryptionKey` and the old one in `deployment.previousEncryptionKeys`, and upgrade.
  2. Run `php spark app:reencrypt` in a kso pod. It writes every stored credential again with the new key.
  3. Remove the old key from `previousEncryptionKeys`, and upgrade. Anyone signed in has to sign in again.
* **A registry, database or mail password** - change it where it lives, enter the new one in kso, and deploy what uses it, so the pull secrets and the deployments' Secrets are written again.
* **A secret environment variable** - enter the new value, and deploy.
