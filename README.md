# Kubernetes Service Orchestrator
The Kubernetes Service Orchestrator (KSO) is a powerful service deployment solution developed using PHP and Vue. 
This service enables interaction with the Kubernetes API and facilitates the effortless deployment of other services within a Kubernetes cluster.

## Features
* Automated deployment of services in Kubernetes clusters.
* User-friendly Vue-based interface for easy configuration and monitoring.
* Handling of Kubernetes API calls and resource management.
* Option for custom configurations and adaptations.

## Install kso
### Create `values.yaml` file
For a complete set of options see [link](https://github.com/4spacesdk/helm-charts/blob/master/charts/kubernetes-service-orchestrator/values.yaml)
```
ingress:
  enabled: true
  hosts:
    - host: chart-example.local
      paths:
        - path: /
          pathType: ImplementationSpecific
  tls:
    - secretName: chart-example-tls
      hosts:
        - chart-example.local

deployment:

  # Valid values: development, production
  environment: ""

  # You need to provide a MySQL database
  database:
    host: ""
    name: ""
    user: ""
    pass: ""

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

resources:
  limits:
    cpu: 500m
    memory: 896Mi
  requests:
   cpu: 100m
   memory: 128Mi

```
### Install
```
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
