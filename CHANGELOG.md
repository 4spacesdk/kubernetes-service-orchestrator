# Changelog

## v0.1.12  ()

### Fixed bugs
* Jobby broken after CI4 upgrade

### Enhancements
* Moved kubernetes.io/ingress.class annotation to spec ingressClassName [link](https://kubernetes.io/docs/concepts/services-networking/ingress/#deprecated-annotation).

### Upgrade guide
1. Deploy new image
    1. No database migration required



## v0.1.11  (2024-01-29)

### Fixed bugs
* Deployments could deploy without init containers
* Added service port type

### Enhancements
* Added more variable to use with environment variables
* Made email service, database service and domain optional for workspace templates

### Upgrade guide
1. Deploy new image
    1. No database migration required



## v0.1.10  (2024-01-28)

### Fixed bugs
* Deployments couldn't deploy without ingress

### Enhancements
* Added support for MSSQL database services
    * `docker-compose.yml` now contains a mssql database service
* Migration jobs is no longer bound to deployment image
    * Specify separate image for migrations at deployment specification
    * Useful if you have database migration code separated from application code
* You can now add init containers to deployment specifications
    * With every init container having their own image, tag and environment variables
* Updated base docker image from `php:8-alpine3.16` to `php:8.3-alpine3.19`
* Updated composer libraries
    * CodeIgniter has been updated from `v4.2.6` to `v4.4.5`

### Upgrade guide
1. Deploy new image
2. Run database migration from inside pod: `cd /var/www/html/ci4 && php spark migrate`
3. If you have multiple pods running this application, you need to clear orm cache in every pod
    1. `cd /var/www/html/ci4 && php spark orm:clear:cache`



## v0.1.9 (2024-01-07)

### Fixed bugs
*

### Enhancements
* Setup quick commands for deployment specifications and execute these directly on deployments
* Small UI adjustments
* Added Helm charts and install guide

### Upgrade guide
1. Deploy new image
2. Run database migration from inside pod: `cd /var/www/html/ci4 && php spark migrate`
3. If you have multiple pods running this application, you need to clear orm cache in every pod
    1. `cd /var/www/html/ci4 && php spark orm:clear:cache`



## v0.1.8 (2024-01-05)

### Fixed bugs
* Fixed https detection behind proxy

### Enhancements
* You can now add environment variables to workspace templates
    * These will be copied to workspace deployments on creation of the workspace
    * These can also be copied from the workspace template environment list

### Upgrade guide
1. Deploy new image
2. Run database migration from inside pod: `cd /var/www/html/ci4 && php spark migrate`
3. If you have multiple pods running this application, you need to clear orm cache in every pod
    1. `cd /var/www/html/ci4 && php spark orm:clear:cache`



## v0.1.7 (2023-12-17)

### Fixed Bugs
*

### Enhancements
* Put labels on workspaces and filter by these

### Upgrade guide
1. Deploy new version
2. Run database migration from inside pod: `cd /var/www/html/ci4 && php spark migrate`
3. If you have multiple pods running this application, you need to clear orm cache in every pod
    1. `cd /var/www/html/ci4 && php spark orm:clear:cache`



## v0.1.6 (2023-12-17)

### Fixed Bugs
*

### Enhancements
* Support for emberstack/kubernetes-reflector to sync certificate secrets across namespaces
* Option to specify cert manager issuer name on individual domains
    * Default value can be set with environment variable

### Upgrade guide
1. Deploy new version
2. Update your `deployment.yaml` with one new environment variable
    1. Name: `CERT_MANAGER_ISSUER_DEFAULT_NAME`
    2. Value: Check your cluster setup. Could be `letsencrypt-production` / `letsencrypt-prod`
3. Redeploy your `deployment.yaml`
4. Run database migration from inside pod: `cd /var/www/html/ci4 && php spark migrate`
5. If you have multiple pods running this application, you need to clear orm cache in every pod
    1. `cd /var/www/html/ci4 && php spark orm:clear:cache`
6. Manually set `issuer_ref_name` on existing domains
