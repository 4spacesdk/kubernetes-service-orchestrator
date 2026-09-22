# Changelog

## Unreleased

### Fixed bugs
* Deploying a workspace that already has its database - after a pause or a terminate - no longer fails with "Database already created"
* Changing the image of a deployment specification was undone on save, and the old image was overwritten with the copy the dialog had loaded. The same for every other form that picks a related row by id
* Deploys: a rollout still running could fail the next with `409 Conflict`, a custom resource without a namespace landed in `default`, an empty or malformed one was accepted, init containers from another registry could not be pulled, and setting a version reported success when the deploy had failed
* Deploys: a field kso spells wrong is refused by the cluster instead of dropped without a word, and two fields every Deployment, Job and CronJob carried are gone
* RBAC: a workspace with no role rules deploys instead of refusing with "Missing Role", terminating one that never had a role binding no longer fails partway through, and rules typed as "get, list" are trimmed rather than refused by Kubernetes
* Contour: a hostname with no routes made an HTTPProxy Kubernetes refused, so the deploy failed
* Certificates: a refused certificate was reported as applied, deleting one did nothing, and the nightly expiry check died on the first certificate cert-manager had not finished
* Volumes: a second volume, and a change to one that already has its disk, are refused when saved instead of failing every later deploy; redeploying no longer unbinds a volume from its claim; a volume with a storage class is reserved for its own claim; and the endpoints read camelCase like every other collection
* Migration jobs: a job did not migrate when it could not reach kso at the start ([#42](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/42)), tag policy "Default" failed the whole deploy, one verified by a pattern ended in a server error and no status at all, and the preview no longer shows differences the cluster filled in itself
* Cron jobs: one bad schedule no longer stops every other job, and a command that does not exist says so in its log instead of looking like it ran
* Auto update: pushes from a registry with a port in its host were missed over Pub/Sub, a tag found early was announced to nobody, an approved update whose deployment is gone no longer deploys an empty one, a tag pattern that cannot compile is refused, and Harbor listed no tags beyond the first ten artifacts
* When kso cannot reach the cluster, or makes a mistake talking to it, the status panels, the shell, the node list and the connection test say what went wrong instead of a server error - which routing step could not be asked, and the cluster's own reason for refusing a manifest. The status panel also died on a resource that has no status yet, or none at all
* A kubeconfig with more than one user authenticates as the one its current context names, not the first in the file
* Database services could not be connected to at all: the test button said no to every service, and a deploy could not create the tenant's database or user. A deleted service answers no, and an unknown driver says which driver
* Sign-in: a page opened while signed out is where the sign-in lands, a deep link with two-factor lands on the link, the refresh token cookie is marked Secure behind a TLS-terminating proxy, a grant carrying no id token is a token rather than a server error, a refused renewal answers 400, and the password form names the first rule the new password breaks
* A token without a scope, and a request nobody had signed in, answer "not allowed" instead of a server error
* Endpoints that could not work are gone: twenty that answered OK and wrote nothing, three that replaced a whole OAuth client, user or gateway and erased every field left out, `PUT /deployments/{id}/ingress`, and five naming code that no longer exists
* Reading something that does not exist answers 404 instead of a resource with every field null; updating something that does not exist is refused instead of answering OK
* Lists: sorting by a field or direction that is not there is refused, `filter=status:active` narrows to that status instead of answering with every workspace, an unreadable label filter says what to write instead, and the environments and Podio field lists carry a count like every other list
* Pod logs kept the start of each line, which was cut off by up to eleven characters, and the live tail shows the same text as the log page. The shell on a pod that is not running says what the cluster said instead of an empty box
* Workspaces: terminating one with no deployments leaves it terminated instead of back in the list as a draft, and names starting with Æ, Ø or Å are allowed again, as are namespaces longer than 15 characters
* Two-factor authentication can be turned off again; removing it used to fail with a database error and leave the second factor in place. The user's own page now knows when it is on, and setting it up again no longer replaces the one in use
* Changing a user role's permissions replaces them: one taken away is taken away, and the same set twice stays the same set
* A record whose related record has been deleted no longer picks up an unrelated one when read a second time
* Post-update actions are skipped rather than crashing on an image without commit identification or version control, and on a commit message with no Podio task link
* Webhooks, the rollout of an approved auto update and the status check after a migration job run from a job queue in the database: they survive a restart instead of being lost, and no longer wait five seconds on each other
* A sign-in whose code could not be exchanged for a token started over by itself and reloaded the tab for ever; it stops and says so. A request that failed after a renewed token was retried for ever too
* Retrying a webhook delivery adds an attempt to the log instead of rewriting the one it retries, stamped with its own time and no leftover response
* A failed save in a dialog was silent, a double click saved twice, and the min scale job logged the wrong schedule's value
* `?app_version=` with nothing after it counted as a version, and a timestamp written with a space between date and time came back a day earlier with the time dropped

### Security
* The sign-in pages show messages from a link as text, so a crafted link can no longer run script on the sign-in page
* A sign-in link can only send the operator on within kso, not to another site
* Links and redirects kso writes use the installation's own address (`BASE_URL`), not whatever host a request names. Other names it is reached on are listed in `ALLOWED_HOSTNAMES`, which the chart fills in
* The app and the sign-in pages send a Content-Security-Policy: the browser runs only scripts served by kso, so an injected script cannot run there
* The sign-in pages load nothing from other hosts: Bootstrap's stylesheet is served by kso, jQuery, Popper and Font Awesome are gone, and the password field's show button works
* Dialogs show text, never HTML. A migration or auto update log could run script in the operator's browser - and the log is written by the customer's own container
* Chart: kso's database password, encryption keys and mail password are read from a Secret instead of written into the pod spec, where anyone who could read the deployment could read them. `deployment.existingSecret` takes one of your own. A password such as `12345` or `true` no longer breaks the install
* Responses no longer carry the debug log outside development - queries, remote servers' answers and error messages went to every caller, signed in or not. The database connection test says why it failed instead
* Swagger - the page and the API description it reads, both served without a sign-in - is off outside development. Set `SWAGGER_ENABLED=true` to keep it
* The two-factor QR code is drawn by kso itself. It used to be fetched from api.qrserver.com with the secret in the url
* Two deployments are never given the same database or database user: a name taken on the same database service gets a hash on the end, long names are no longer merely cut, and a database or user already on the server is refused instead of handed over
* A password set through the API is hashed before it is written and held to the same rules as a renewal; a short one used to be stored in plain text. Any left from before are hashed and must be renewed at the next sign-in
* The sign-in form no longer says whether a username exists, by its message or by how long it takes, and after ten failed attempts in a row a username - or its two-factor code - is refused for 15 minutes. Every attempt is kept for 90 days in `sign_in_attempts`: who, whether it worked, address and browser
* A forgotten password is replaced through a one-time link in the mail, valid for an hour, instead of on the spot with the new one mailed in plain text. Asking on someone else's behalf changes nothing, and the form no longer says whether an address has an account
* The cron endpoint no longer runs every scheduled job for whoever asks: it takes a token the chart generates and gives to both kso and the scheduler. The endpoint that ran a single named job is gone; nothing called it
* A customer's database password is drawn from the system's own randomness; it used to come from a generator whose output can be worked out from enough of it
* Responses carry the usual browser protections - `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `X-Permitted-Cross-Domain-Policies`, and HSTS when the request came over TLS - and no longer announce the Apache and PHP versions
* Stored credentials are encrypted in the database: registry, database and email passwords, Podio and GitHub secrets, webhook tokens, a tenant's database password and the two-factor secret. The key comes from the installation's own `ENCRYPTION_KEY` - **it has to be set, and kept**
* Environment variables can be marked secret: the value is never sent back to the UI, and saving the list without it keeps it. Every variable's value is encrypted in the database. "Copy to deployments" on a workspace template takes the template's value instead of one in the url
* Secret environment variables reach the pods through a Secret per workload - the Deployment or KService, each CronJob, each Job - owned by it, instead of in the pod spec. So do variables that take `${database.pass}` or `${emailService.pass}`, marked or not. A changed value rolls the pods. The preview shows the Secret with its values hidden and marks the ones that change, and hides them in a workload deployed before. A custom resource's preview hides what `${database.pass}` and `${emailService.pass}` fill in
* Stored credentials are no longer handed to anyone signed in: a database or email service's password, a Podio client secret and app token, a webhook's bearer token - in the delivery log too - and an OAuth client secret are write-only now. A form says whether one is stored and keeps it when left empty, and the OAuth clients list no longer prints the secret in a column
* The sign-in sends a random state and exchanges only a code that comes back with it. It used to be the same word every time, so a code started elsewhere could be handed to the login page
* An access token in the url - kso in an iframe - is taken out of the url once read, and the sign-in no longer sends its PKCE verifier to authorize, only when it exchanges the code
* Webhooks and Harbor are only called over http and https, and not on kso's own pod or the cloud's metadata service. A webhook url to a local file used to put the file's contents in the delivery log. A delivery that is refused says why in the log
* The image checks what it downloads: Microsoft's SQL Server packages against pinned checksums, fetched with the certificate checked, and Composer and gke-auth at pinned versions instead of whatever an installer script or `@latest` gave that day
* The container runs as www-data instead of root, with Apache on port 8080. The chart sets `runAsNonRoot`, drops every capability and forbids privilege escalation by default
* Chart: the cron job runs as non-root from a pinned curl image, without the service account token, and a NetworkPolicy that admits only kso's two ports can be turned on with `networkPolicy.enabled`. A pod that cannot reach its database is taken out of the Service
* Apache serves only `/api` and `/app`. The rest of the application's files - the PHP dependencies and the tests among them - could be listed and run by url
* A migration job reports that it started and ended with a token of its own, given to its pod through the job's Secret. Without it the report is refused, so nobody else can end a job and set off its post-update commands. A job started before the upgrade cannot report back - rerun it
* Live updates go through Centrifugo: a browser connects with its sign-in token, checked against kso's published keys, and can only listen. The WAMP router, its shared secret and the ZeroMQ extension are gone
* Security-related improvements

### Enhancements
* Container Images: the tags your deployments run are scanned for known vulnerabilities with Trivy every night, and on "Scan now". The list shows the counts per tag, and a dialog lists every finding with the version that fixes it, and a graph of critical and high over the last year
* Container Images: the list shows how many deployments run each image, with a click to list them, drops the Registry column, and moves the pull secret and version control to icons
* Container Registries: credentials shared by all images, import of images, auto update set up by kso, optional pull secrets
* Container Images: list tags with when each was pushed, and the registry's reason when it refuses. The deployment version picker shows when each tag was pushed too ([#64](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/64))
* GitHub Integrations: one GitHub App per organisation
* Lists: search, filters, page and sort kept in the url, sortable columns, clickable names ([#53](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/53)), keyboard shortcuts
* Deployments: a status filter, so a terminated workspace's deployments are out of the way by default
* Update the version of several deployments at once
* Pause a workspace: it is shut down like Terminate, but the pause is remembered, so auto update leaves it alone and the list says Paused
* Run a deployment's cron job now, from the Resources dialog ([#50](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/50))
* Annotations on gateways ([#65](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/65))
* Duplicate on most setup entities
* Faster start: the app loads half as much before it shows, and dialogs reuse the lists they pick from
* Terminate and Delete moved into a menu on workspaces and gateways
* The sign-in form keeps the e-mail after a wrong password, with the cursor in the password field
* Upgraded to PHP 8.5, Alpine 3.24 and CodeIgniter 4.7. The image also builds on arm64
* The access log records the sign-in redirects
* Added unit, database and integration test suites

### Upgrade guide
1. Set `deployment.encryptionKey` in the chart to 32 characters of your own - `openssl rand -base64 24` makes one on Linux or macOS. It is what the stored credentials are encrypted with, and it has to stay the same afterwards - change it and they cannot be read back. Rotating it later: the old one in `deployment.previousEncryptionKeys`, then `php spark app:reencrypt` - see "If a secret leaks" in the README
2. Deploy new image
3. Run migrations [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm). Registry credentials and the GitHub App move to Integrations automatically
4. Harbor and Azure: open each container registry and click "Set up auto update", then remove the old webhooks from the registry
5. Artifact Registry: the service account only needs Artifact Registry Reader now
6. Swagger is off. To keep it, set `deployment.config.swaggerEnabled: true` in the chart
7. If kso is also reached on a hostname the chart's routing does not list, add it to `deployment.config.extraHostnames`
8. Image scanning keeps Trivy's databases on a 4Gi volume (`deployment.imageScanning.cacheSizeLimit`), fetched again when the pod starts - 1.3 GB, and 1.4 GB more once an image with Java in it is scanned. The chart now sets `resources` by default: 256Mi requested and a 1Gi memory limit, measured at about 90Mi idle and 110-180Mi more during a scan. Helm merges them with your own `resources`; set `resources: null` to go without
9. kso needs `create`, `get`, `update`, `patch`, `delete` and `list` on `secrets`, and `update` on the `finalizers` of deployments, jobs, cronjobs and Knative services. The chart's ClusterRole has them now; if you grant kso's rights yourself, add them. Knative's rights come with `knative.enabled: true` - if you gave kso Knative Services through `clusterrole.additionalRules`, set that instead

10. The chart runs Centrifugo as a sidecar and routes `/connection` to it instead of `/socket` to port 9100. If you route to kso yourself, send `/connection` to the Service's `push` port (8000) with WebSockets allowed, and drop `/socket`
11. The image runs as www-data (uid 82) and Apache listens on 8080. The chart's Service still answers on 80, so routing through it needs nothing. If you run the image outside the chart, map to 8080; if you set `securityContext` or `podSecurityContext` yourself, keep `runAsUser: 82` and `fsGroup: 82`

### Notes
* An image built for arm64 has no MSSQL driver
* `SSL_REDIRECT` is gone. It compared the request's host with the whole of `BASE_URL`, scheme included, so it never redirected; redirecting to HTTPS is the ingress's job



## v1.8.11 (2026-09-16)

### Enhancements
* Security-related improvements

### Upgrade guide
1. Deploy new image



## v1.8.10 (2026-09-15)

### Fixed bugs
* A workspace served on the apex hostname of a domain with `https_redirect` was attached to the wildcard listener, which does not match the apex. The HTTPRoute now attaches to the apex listener.

### Enhancements
* Workspace aliases redirect (301) to the workspace hostname on Gateway API. An alias is either a subdomain or a hostname on the workspace domain. The `Gateway HTTP Route` step creates an HTTPRoute with a `RequestRedirect` per alias, and deletes redirects for removed aliases on the next deploy.
* The workspace menu item `Ingress` is renamed `Domain`, since it also applies to Gateway API.

### Upgrade guide
1. Deploy new image
2. Run migrations [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. kso needs `list` on `httproutes.gateway.networking.k8s.io` to find redirects for removed aliases. The install chart in `4spacesdk/helm-charts` now grants kso access to `gateways`, `httproutes` and `referencegrants` (gateway.networking.k8s.io).
4. Deploy the `Gateway HTTP Route` step on workspaces with aliases.



## v1.8.9 (2026-07-22)

### Enhancements
* Deployment specifications can now set a `Backend timeout (seconds)`. On a GKE cluster, kso generates a `GCPBackendPolicy` (networking.gke.io/v1) for services behind a GKE Gateway, raising the GCP backend service response timeout above its 30s default. Without it, any request that runs longer than 30s is cut off once it goes through the Gateway, even though the same request succeeds pod-to-pod.

### Upgrade guide
1. Deploy new image
2. Run migrations [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. On GKE, grant kso's service account access to `gcpbackendpolicies.networking.gke.io` (get, list, create, update, delete). Without it the deploy step fails with a 403. See the install chart in `4spacesdk/helm-charts`.

### Notes
* Existing deployments are unaffected until a specification is given a backend timeout. Until then no GCPBackendPolicy is generated, and GKE keeps using its default 30s backend timeout.



## v1.8.8 (2026-07-16)

### Fixed bugs
* Added pagination to GitHub list repositories to deal with large organizations

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.8.7 (2026-07-15)

### Fixed bugs
* Deploying a deployment created an empty workspace. When a deployment's status cascaded to its workspace, the workspace relation was an unloaded, id-less entity, and the status update saved it as a brand new row.

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.8.6 (2026-07-15)

### Fixed bugs
* GKE Gateway reported ports that do not speak HTTP, such as websocket ports unhealthy. A GKE Gateway derives one health check per (Service, port) referenced from an HTTPRoute, and the default check is an HTTP GET which a websocket port can never answer.

### Enhancements
* Service ports can now declare a health check type (HTTP with a request path, or TCP). On a GKE cluster, kso generates a `HealthCheckPolicy` (networking.gke.io/v1) for services behind a GKE Gateway. A HealthCheckPolicy has no per-port selector, so a single TCP port puts the whole service on a TCP check, which is the only configuration that keeps an HTTP port and a websocket port healthy at the same time.
* New system setting `Hosting Provider`. The well-known providers can be picked from a list, and anything else can be typed in. Only `gke` changes behavior today, by enabling the health check configuration.

### Upgrade guide
1. Deploy new image
2. Run migrations [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. Set `Hosting Provider` under Setup -> System
4. On GKE, grant kso's service account access to `healthcheckpolicies.networking.gke.io` (get, list, create, update, delete). Without it the deploy step fails with a 403. See the install chart in `4spacesdk/helm-charts`.

### Notes
* Existing deployments are unaffected until a service port is given a health check type. Until then no HealthCheckPolicy is generated, and GKE keeps using its default health check.



## v1.8.5 (2026-07-14)

### Fixed bugs
*

### Enhancements
* Implemented sharding on HTTPRoute rules (caused by limit of 16 rules)

### Upgrade guide
1. Deploy new image



## v1.8.4 (2026-07-14)

### Fixed bugs
*

### Enhancements
* Add Gateway spec.addresses [Issue #66](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/66)
* Enhanced status detection for deployments and workspaces

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.8.3 (2026-06-11)

### Fixed bugs
* Wrong parentRef in HttpRoute

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.8.2 (2026-06-11)

### Fixed bugs
*

### Enhancements
* Now using both base domain and wildcard for gateway listeners

### Upgrade guide
1. Deploy new image



## v1.8.1 (2026-06-08)

### Fixed bugs
* Fix a bug related to the GitHub App integration installation url

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.8.0 (2026-06-08)

### Fixed bugs
* 

### Enhancements
* Add support for GitHub App integration via manifests
  * Removed legacy GitHub Personal Access Token (PAT) authentication. Users are now required to use GitHub Apps.
* Add support for envoy-gateway [Issue #62](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/62)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
3. If using GitHub integration for container images: Install GitHub App and connect existing container images



## v1.7.0 (2026-01-26)

### Fixed bugs
* Fix Volume validation for "nfs_server" and "nfs_path" [Issue #61](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/61)

### Enhancements
* Updated `4spacesdk/ci4authextension` from v1.2.1 to v1.2.2
* Add `database.port` as env var variable [Issue #59](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/59)
* Add support for image repository "Harbor" [Issue #60](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/60)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.6.3 (2026-01-24)

### Fixed bugs
*

### Enhancements
* Allow for database port to be configured by environment variable

### Upgrade guide
1. Deploy new image



## v1.6.2 (2025-10-15)

### Fixed bugs
* Added names to cronjobs to fix collision in Jobby

### Enhancements
*

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.6.1 (2025-10-15)

### Fixed bugs
* Deployment `knative_scheduled_minscale_is_enabled` did not get set correctly

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.6.0 (2025-10-15)

### Fixed bugs
* Added missing option for knativeConcurrencyLimitSoft and knativeConcurrencyLimitHard on deployment resource management
* "Create Deployment for Workspace"-dialog issue with name input [Issue #57](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/57)

### Enhancements
* Scheduled minScale management for Knative Services [Issue #58](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/58)
* Add a link to the GitHub release (with changelog) from the webUI [Issue #52](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/52)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.12 (2025-08-29)

### Fixed bugs
*

### Enhancements
* CronJobs can now be added directly to a deployment

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.11 (2025-08-18)

### Fixed bugs
*

### Enhancements
* Added option to specify imagePullPolicy at all levels

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.10 (2025-08-16)

### Fixed bugs
*

### Enhancements
* Add more flexible storage handling [Issue #56](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/56)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.9 (2025-08-15)

### Fixed bugs
*

### Enhancements
* Add more flexible storage handling [Issue #56](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/56)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.8 (2025-08-13)

### Fixed bugs
*

### Enhancements
* Added timeoutPolicy fields for Contour HttpProxy Routes [Issue #55](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/55)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.7 (2025-08-05)

### Fixed bugs
*

### Enhancements
* Annotations can now be set at both deployment level and pod level

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.6 (2025-06-20)

### Fixed bugs
*

### Enhancements
* InitContainers can now be added to a migration job

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.5 (2025-06-19)

### Fixed bugs
*

### Enhancements
* Added `fsGroup` to security context

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



## v1.5.4 (2025-06-15)

### Fixed bugs
* Added security context for KService containers

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.5.3 (2025-06-15)

### Fixed bugs
* KService now uses ports from http proxy routes and not static setting containerPort 80

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.5.2 (2025-06-13)

### Fixed bugs
*

### Enhancements
* Added security context fields to ContainerImage allowing for `runAsUser`, `runAsGroup`, `allowPrivilegeEscalation` and `readOnlyRootFilesystem` to be set at container level

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)
 


## v1.5.1 (2025-06-07)

### Fixed bugs
* Corrected cronjob schedule hint [Issue #49](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/49)
* Add imagePullSecrets to CronJobs [Issue #51](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/51)

### Enhancements
*

### Upgrade guide
1. Deploy new image



## v1.5.0 (2025-03-20)

### Fixed bugs
* Do not fail if deploying/terminating workspaces that do not have ClusterRole/Role [Issue #45](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/45)

### Enhancements
* More validation to workspace name [Issue #47](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/47)
* Add certificate expiration notifications [Issue #48](https://github.com/4spacesdk/kubernetes-service-orchestrator/issues/48)

### Upgrade guide
1. Deploy new image
2. Migrate database [(Guide)](https://github.com/4spacesdk/kubernetes-service-orchestrator?tab=readme-ov-file#migrate-database-helm)



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
