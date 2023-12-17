# Changelog

## v0.1.5 (2023-12-17)

### Fixed Bugs

* 

### Enhancements

* Support for emberstack/kubernetes-reflector to sync certificate secrets across namespaces
* Option to specify cert manager issuer name on individual domains
  * Default value can be set with environment variable 

### Upgrade guide

1. Update your `deployment.yaml` with one new environment variable
   1. Name: `CERT_MANAGER_ISSUER_DEFAULT_NAME`
   2. Value: Check your cluster setup. Could be `letsencrypt-production` / `letsencrypt-prod`
2. Redeploy your `deployment.yaml`
3. Run database migration from inside pod: `cd /var/www/html/ci4 && php spark migrate`
4. If you got multiple pods running this application, you need to clear orm cache in every pod
   1. `cd /var/www/html/ci4 && php spark orm:clear:cache`
