# Changelog

## v1.4.8 (2025-02-14)

### Fixed bugs
* Added missing deployment-step-trigger constants

### Enhancements
* 

### Upgrade guide
1. Deploy new image



## v1.4.7 (2025-02-12)

### Fixed bugs
*

### Enhancements
* KNative Services now support init containers

### Upgrade guide
1. Deploy new image



## v1.4.6 (2025-02-11)

### Fixed bugs
*

### Enhancements
* Volumes V2
    * We can now manage volumes at deployment specification level
    * Init containers now support volumes
    * KNative Services now support volumes

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.4.5 (2025-02-07)

### Fixed bugs
*

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.4.4 (2025-02-07)

### Fixed bugs
*

### Enhancements
* Updated zmq-server composer dependencies
* Added run-job feature
    * Start kubernetes job and get log as response
    * Internally this feature handles
        * Job creation
        * Wait for completion
        * Fetch pod logs
        * Delete pod and job
        * Return log
    * This feature is used to handle interaction with deployments that run on zero replicas (knative serving)

### Upgrade guide
1. Deploy new image



## v1.4.3 (2025-02-06)

### Fixed bugs
* Fixed a bug in `waitForPodsToStabilize` when using knative with 0 replicas

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.4.2 (2025-02-03)

### Fixed bugs
* Fixed a bug regarding knative resource limits

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.4.1 (2025-02-03)

### Fixed bugs
*

### Enhancements
* Added option to specify container name for post migration commands

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.4.0 (2025-02-03)

### Fixed bugs
* Script "install composer dependencies" tried to cd ../zmq [Issue #39](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/39)

### Enhancements
* Support for KNative Serving [Issue #38](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/38)
  * Including separation of network layers; support for nginx-ingress, istio, contour
  * Streamlining of external and internal access configuration
  * This is a huge enhancement!
* Support for Apple Silicon (M1, M2 etc.) [Issue #40](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/40)
* Allow for custom resources to configuration related resources, such as database, cronjob, network access, etc.
* Bulk edit feature for environment variables [Issue #43](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/43)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. Breaking changes
   * Deployments must now belong to a workspace. 
   * Following deployment configurations has been removed
     * Domain configuration. Deployments will use domain + subdomain from workspace
     * Custom Resource. Deployments will use setting from deployment specification



## v1.3.3 (2025-01-02)

### Fixed bugs
* Added `waitForPodsToStabilize` function before executing Post Update Actions. This prevents KSO for using a terminating pod as commit identifier.

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.3.2 (2024-12-27)

### Fixed bugs
* Added `${deployment.name}` as variable to commands, environment variables and command arguments.

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.3.1 (2024-12-27)

### Fixed bugs
* Added deployment name as prefix to cronjob name. To avoid collision in namespaces.

### Enhancements
*

### Upgrade guide
1. Deploy new image

    

## v1.3.0 (2024-12-27)

### Fixed bugs
*

### Enhancements
* Cluster role now has unique names to avoid collision between deployments [Issue #35](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/35)
* Role and RoleBinding is now part of deployment RBAC
* Deployment Specification can now include annotations for ingresses and deployment [Issue #36](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/36)
* CronJobs V2. Previously a deployment specification could have zero or one cronjob associated. This has now been refactored to multiple cronjob, with a lot more available settings. [Issue #37](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/37)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. Migrate cronjob for existing deployment specifications
    * To clean up legacy cronjob: Create a new cronjob with the same name as the deployment. You can then terminate the cronjob from deployment resource list.



## v1.2.5 (2024-12-02)

### Fixed bugs
*

### Enhancements
* New logo
* Updated composer dependencies
    * 4spacesdk/ci4authextension:v1.2.0 -> 4spacesdk/ci4authextension:v1.2.1

### Upgrade guide
1. Deploy new image



## v1.2.4 (2024-11-06)

### Fixed bugs
*

### Enhancements
* Include project name in Google Cloud Pub/Sub subscription name, to allow for multiple kso instances using the same Pub/Sub

### Upgrade guide
1. Deploy new image
2. Resave all container images that rely on artifact registry to trigger new subscription



## v1.2.3 (2024-11-06)

### Fixed bugs
* Workspaces in draft mode no longer receives auto updates

### Enhancements
* Improved workspace filter in UI

### Upgrade guide
1. Deploy new image



## v1.2.2 (2024-10-14)

### Fixed bugs
* Fixed missing 2FA when opening user edit from profile button

### Enhancements


### Upgrade guide
1. Deploy new image



## v1.2.1 (2024-10-12)

### Fixed bugs


### Enhancements
* Improved 2FA secret name in authenticator apps

### Upgrade guide
1. Deploy new image



## v1.2.0 (2024-10-12)

### Fixed bugs


### Enhancements
* Updated composer dependency `4spacesdk/ci4authextension` to v1.2.0
* Two-factor authentication [Issue #33](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/33)
* Updated zeromq fra v4.1.4 to v4.1.8
* Migrated from archive.org to GitHub downloads


### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.1.2 (2024-10-12)

### Fixed bugs
* Client did not log out after failed token refresh

### Enhancements


### Upgrade guide
1. Deploy new image



## v1.1.1 (2024-10-08)

### Fixed bugs

### Enhancements
* Added more webhook triggers [Issue #32](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/32)

### Upgrade guide
1. Deploy new image



## v1.1.0 (2024-10-07)

### Fixed bugs

### Enhancements
* Added labels to deployments and deployment specifications [Issue #31](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/31)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.0.3 (2024-10-02)

### Fixed bugs

### Enhancements
* Updated composer dependencies
    * `4spacesdk/ci4ormextension`
    * `4spacesdk/ci4authextension`
* Updated alpine from v3.19 to v3.20
* Updated php from v8.3.8 to v8.3.12

### Upgrade guide
1. Deploy new image



## v1.0.2 (2024-09-30)

### Fixed bugs
* Added missing PKCE fields

### Enhancements

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.0.1 (2024-09-30)

### Fixed bugs
* Fix migration issue

### Enhancements

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.0.0 (2024-09-28)

### Fixed bugs
* Typo [Issue #27](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/27)
* Cannot remove "default version" for deployment specification on workspace template [Issue #26](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/26)

### Enhancements
* Added support for deploying custom resources
* Upgraded packages
    * Vue 3.2.45 -> 3.4.34
    * Vuetify 3.1.0 -> 3.6.13
    * and many more...
* Automated updates [Issue #19](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/19)
    * Integrates with Google Cloud Artifact Container Registry and Azure Container Registry
    * Optional approval step
* Test database connection feature [Issue #23](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/23)
* Github integration credentials has been migrated from environment variables to container image properties [Issue #29](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/29)
* Podio integration has been moved from environment variables to its own object [Issue #28](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/28)
    * You can now create podio integrations directly in the web ui
* Post Update Actions
    * Before this release, KSO could update a Podio item, based on url found in commit message
    * Now you can create post update actions to accomplice the same and much more!
        * Features:
            * Add Comment with url to GitHub commit
            * Update fields based on conditions. For example change status from "development" to "test"
* Authorization flow is changed from implicit to authorization flow with PKCE [Issue 30](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/30)

### Breaking changes
* Google Artifact Registry integration credentials has been moved from environment variables to container image properties
    * Update all your container images with these properties
* The property `git_repo` for deployment specification has been moved to container image
    * Migrations will handle this change
* Github integration credentials has been moved from environment variables to container image properties
    * Migrations will handle this change
* Podio integration has been moved from environment variables to post update actions
    * You will have to create podio integration manually in the web ui after release and create post update actions

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. Check for breaking changes


## v0.1.17 (2024-06-10)

### Fixed bugs
* Fixed deployment status check
* Increased timeout for post migration job hook
* Fixed email issue for AWS SES (Port 465)
* Fixed typo

### Enhancements

### Upgrade guide
1. Deploy new image



## v0.1.16 (2024-04-02)

### Fixed bugs
* Fix missing "image pull secret" for migration jobs

### Enhancements

### Upgrade guide
1. Deploy new image



## v0.1.15 (2024-04-02)

### Fixed bugs
* Fix ingress redirect tls secret name
* Remove ingress-redirect when deployment alias is removed [Issue #18](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/18)

### Enhancements
* Added "Password renewal" feature [Issue #20](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/20)
* Added "Forgot password" feature [Issue #20](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/20)
* New webhook event "workspace.updated" [Issue #16](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/16)
* Added support for new webhook http methods: get, patch, put, delete [Issue #17](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/17)

### Upgrade guide
1. Deploy new image
2. Migrate database. [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v0.1.14 (2024-02-27)

### Fixed bugs
* Fix issue with migration jobs hanging if no post commands

### Enhancements
* Lowered default log level to avoid excessive deprecation logs "[DEPRECATED] Creation of dynamic property"
* Allow for larger migration logs (TEXT -> LONGTEXT)

### Upgrade guide
1. Deploy new image
2. Migrate database. [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v0.1.13 (2024-02-27)

### Fixed bugs
*

### Enhancements
* Add labels to workspace templates [Issue #15](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/15)
* Add configuration for migration job log verification [Issue #10](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/10)
* Add RBAC for KSO Users [Issue #14](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/14)

### Upgrade guide
1. Deploy new image
2. Migrate database. [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v0.1.12 (2024-02-21)

### Fixed bugs
* Jobby broken after CI4 upgrade

### Enhancements
* Moved kubernetes.io/ingress.class annotation to spec ingressClassName [link](https://kubernetes.io/docs/concepts/services-networking/ingress/#deprecated-annotation).
* You can now specify `imagePullSecret` for container images
    * Set default value with `IMAGE_PULL_SECRET_DEFAULT_NAME` environment variable or set value in helm `values.yaml`

### Upgrade guide
1. Deploy new image
2. Migrate database. [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v0.1.11 (2024-01-29)

### Fixed bugs
* Deployments could deploy without init containers
* Added service port type

### Enhancements
* Added more variable to use with environment variables
* Made email service, database service and domain optional for workspace templates

### Upgrade guide
1. Deploy new image
2. No database migration required



## v0.1.10 (2024-01-28)

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
