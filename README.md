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
For a complete set of options see [link](https://github.com/4spacesdk/kubernetes-service-orchestrator/blob/main/charts/kso/values.yaml)
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

  env: [ ]
#  env:
#    - name: ""
#      value: ""

```
### Install using helm
```
helm upgrade --install kso 4spacesdk/kso --values=values.yaml --namespace kso --create-namespace
```

### Delete kso
```
helm delete kso
```
