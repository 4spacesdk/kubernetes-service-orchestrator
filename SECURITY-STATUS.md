# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-11-23 15:39 UTC

Main at: `39fe4da`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 1 |
| JavaScript · vue/package-lock.json | 0 | 0 | 2 | 0 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 89 | 92 | 115 | 52 | 1 |

## PHP · ci4/composer.lock

*abandoned: vierbergenlars/php-semver*


<details><summary>1 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| unknown | [grpc/grpc/2026-10-28-1.yaml](https://github.com/grpc/grpc/security/advisories/GHSA-gvrc-7wf9-p7hg) | grpc/grpc | 1.82.0 | not <1.84.1 | gRPC PHP: bundled CA store trusts compromised eMudhra intermediate CAs |

</details>

## JavaScript · vue/package-lock.json

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| medium | [GHSA-3g3m-2fjr-6pw4](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4) | glob | 11.1.0 | not >=9.0.0 <=14.0.1 | Glob: AbortSignal cancellation bypass allows post-timeout filesystem traversal |
| medium | [GHSA-g3hv-75wg-m249](https://github.com/advisories/GHSA-g3hv-75wg-m249) | minimatch | 10.2.6 | not >=7.0.0 <11.0.1 | minimatch has potential denial of service via globparts amplification |

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from 48289e0 - behind main, whose latest push is not built yet · alpine 3.24.4 · 4spaces/kubernetes-service-orchestrator@sha256:47c41427201a36e15e163d0583f1aa3b3f8f660db24edd74070d69ce2ae59b5e*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [CVE-2026-119618](https://avd.aquasec.com/nvd/cve-2026-119618) | apache2 | 2.4.69-r0 | 2.4.71-r0 | httpd:2.4/mod_http2: mod_http2: httpd: Remote code execution in mod_http2 |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| critical | [CVE-2026-115560](https://avd.aquasec.com/nvd/cve-2026-115560) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Session serializer mismatch can lead to remote code execution |
| critical | [CVE-2026-126714](https://avd.aquasec.com/nvd/cve-2026-126714) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Partially overwritten session files can lead to remote code execution |
| critical | [CVE-2026-129289](https://avd.aquasec.com/nvd/cve-2026-129289) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM request smuggling through an unread HTTP request body) |
| critical | [CVE-2026-129496](https://avd.aquasec.com/nvd/cve-2026-129496) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Timezone cache use-after-free can lead to remote  code execution |
| high | [CVE-2026-106023](https://avd.aquasec.com/nvd/cve-2026-106023) | apache2 | 2.4.69-r0 | 2.4.71-r0 | httpd: httpd: Denial of Service via NULL pointer dereference in mod_md |
| high | [CVE-2026-86144](https://avd.aquasec.com/nvd/cve-2026-86144) | libxml2 | 2.13.9-r2 | 2.13.9-r3 | libxml2: libxml2: Information disclosure, SSRF, or denial of service due to improper parseFlags propagation. |
| high | [CVE-2026-86144](https://avd.aquasec.com/nvd/cve-2026-86144) | libxml2-dev | 2.13.9-r2 | 2.13.9-r3 | libxml2: libxml2: Information disclosure, SSRF, or denial of service due to improper parseFlags propagation. |
| high | [CVE-2026-86144](https://avd.aquasec.com/nvd/cve-2026-86144) | libxml2-utils | 2.13.9-r2 | 2.13.9-r3 | libxml2: libxml2: Information disclosure, SSRF, or denial of service due to improper parseFlags propagation. |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| high | [CVE-2026-127960](https://avd.aquasec.com/nvd/cve-2026-127960) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Denial of service through folded HTTP response headers |
| high | [CVE-2026-128064](https://avd.aquasec.com/nvd/cve-2026-128064) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Mixed-case HTTPS and FTPS URLs can expose data in plaintext |
| high | [CVE-2026-129291](https://avd.aquasec.com/nvd/cve-2026-129291) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM epoll use-after-free can lead to local  privilege escalation |
| high | [CVE-2026-129481](https://avd.aquasec.com/nvd/cve-2026-129481) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Use-after-free after interrupted session shutdown |
| medium | [CVE-2026-109163](https://avd.aquasec.com/nvd/cve-2026-109163) | apache2 | 2.4.69-r0 | 2.4.71-r0 | httpd: httpd: Denial of Service via stack-based buffer overflow in mod_vhost_alias |
| medium | [CVE-2026-97033](https://avd.aquasec.com/nvd/cve-2026-97033) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: golang: cmd/go: Arbitrary code execution via unsafe cgo compiler and linker flags |
| medium | [CVE-2026-97034](https://avd.aquasec.com/nvd/cve-2026-97034) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: golang: cmd/go: arbitrary command execution via untrusted GCC spec files |
| medium | [CVE-2026-97035](https://avd.aquasec.com/nvd/cve-2026-97035) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: golang: cmd/go: arbitrary code execution via crafted cgo directives |
| medium | [CVE-2026-97037](https://avd.aquasec.com/nvd/cve-2026-97037) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: golang: cmd/go: arbitrary code execution via malicious cgo linker flags |
| medium | [CVE-2026-97038](https://avd.aquasec.com/nvd/cve-2026-97038) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: golang: cmd/go: Arbitrary code execution via crafted linker inputs |
| medium | [CVE-2026-97039](https://avd.aquasec.com/nvd/cve-2026-97039) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: golang: cmd/go: arbitrary code execution via crafted pkg-config directives |
| medium | [CVE-2026-97040](https://avd.aquasec.com/nvd/cve-2026-97040) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: cmd/link: golang: golang: Arbitrary code execution via crafted .syso files |
| medium | [CVE-2026-97041](https://avd.aquasec.com/nvd/cve-2026-97041) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/compile: cmd/link: golang: golang: Arbitrary code execution via external linker flag injection |
| medium | [CVE-2026-97042](https://avd.aquasec.com/nvd/cve-2026-97042) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/cgo: cmd/compile: cmd/link: golang: Go: Arbitrary code execution via unsafe linker arguments on Windows |
| medium | [CVE-2026-97043](https://avd.aquasec.com/nvd/cve-2026-97043) | go | 1.26.10-r0 | 1.26.11-r0 | cmd/go: cmd/compile: golang: golang: arbitrary code execution via crafted cgo compiler directives |
| medium | [CVE-2026-97054](https://avd.aquasec.com/nvd/cve-2026-97054) | go | 1.26.10-r0 | 1.26.11-r0 | net/http: golang: net/http: Denial of Service via race condition when handling oversized HTTP/1 requests |
| medium | [CVE-2026-128085](https://avd.aquasec.com/nvd/cve-2026-128085) | go.opentelemetry.io/otel/sdk | v1.46.0 | 1.47.0 | go.opentelemetry.io/otel/sdk: go.opentelemetry.io/otel/sdk/log: go.opentelemetry.io/otel/trace: OpenTelemetry-Go: Denia… |
| medium | [CVE-2026-128085](https://avd.aquasec.com/nvd/cve-2026-128085) | go.opentelemetry.io/otel/trace | v1.46.0 | 1.47.0 | go.opentelemetry.io/otel/sdk: go.opentelemetry.io/otel/sdk/log: go.opentelemetry.io/otel/trace: OpenTelemetry-Go: Denia… |
| medium | [CVE-2026-107496](https://avd.aquasec.com/nvd/cve-2026-107496) | libcrypto3 | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via crafted EC certificate in CMS encryption |
| medium | [CVE-2026-95691](https://avd.aquasec.com/nvd/cve-2026-95691) | libcrypto3 | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via overlapping QUIC CRYPTO frame processing |
| medium | [CVE-2026-95692](https://avd.aquasec.com/nvd/cve-2026-95692) | libcrypto3 | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Denial of Service via out-of-bounds read in AES-GCM-SIV |
| medium | [CVE-2026-107496](https://avd.aquasec.com/nvd/cve-2026-107496) | libssl3 | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via crafted EC certificate in CMS encryption |
| medium | [CVE-2026-95691](https://avd.aquasec.com/nvd/cve-2026-95691) | libssl3 | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via overlapping QUIC CRYPTO frame processing |
| medium | [CVE-2026-95692](https://avd.aquasec.com/nvd/cve-2026-95692) | libssl3 | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Denial of Service via out-of-bounds read in AES-GCM-SIV |
| medium | [CVE-2026-107496](https://avd.aquasec.com/nvd/cve-2026-107496) | openssl | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via crafted EC certificate in CMS encryption |
| medium | [CVE-2026-95691](https://avd.aquasec.com/nvd/cve-2026-95691) | openssl | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via overlapping QUIC CRYPTO frame processing |
| medium | [CVE-2026-95692](https://avd.aquasec.com/nvd/cve-2026-95692) | openssl | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Denial of Service via out-of-bounds read in AES-GCM-SIV |
| medium | [CVE-2026-107496](https://avd.aquasec.com/nvd/cve-2026-107496) | openssl-dev | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via crafted EC certificate in CMS encryption |
| medium | [CVE-2026-95691](https://avd.aquasec.com/nvd/cve-2026-95691) | openssl-dev | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Denial of Service via overlapping QUIC CRYPTO frame processing |
| medium | [CVE-2026-95692](https://avd.aquasec.com/nvd/cve-2026-95692) | openssl-dev | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Denial of Service via out-of-bounds read in AES-GCM-SIV |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-128090](https://avd.aquasec.com/nvd/cve-2026-128090) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: PHAR OpenSSL signature bypass via algorithm downgrade |
| medium | [CVE-2026-129292](https://avd.aquasec.com/nvd/cve-2026-129292) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: PHP-FPM status endpoint XSS via unescaped user and script fields) |
| medium | [CVE-2026-129501](https://avd.aquasec.com/nvd/cve-2026-129501) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: Double free during DOM XML serialization) |
| medium | [CVE-2026-129538](https://avd.aquasec.com/nvd/cve-2026-129538) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: PHP-CGI out-of-bounds read and write during PATH_INFO correction |
| medium | [CVE-2026-97054](https://avd.aquasec.com/nvd/cve-2026-97054) | stdlib | v1.27.3 | 1.26.11, 1.27.4 | net/http: golang: net/http: Denial of Service via race condition when handling oversized HTTP/1 requests |

<details><summary>53 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [CVE-2026-88822](https://avd.aquasec.com/nvd/cve-2026-88822) | libcrypto3 | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Information disclosure via uninitialized memory in QUIC peer detection |
| low | [CVE-2026-88823](https://avd.aquasec.com/nvd/cve-2026-88823) | libcrypto3 | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Message authentication bypass via zero-length AAD update |
| low | [CVE-2026-88822](https://avd.aquasec.com/nvd/cve-2026-88822) | libssl3 | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Information disclosure via uninitialized memory in QUIC peer detection |
| low | [CVE-2026-88823](https://avd.aquasec.com/nvd/cve-2026-88823) | libssl3 | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Message authentication bypass via zero-length AAD update |
| low | [CVE-2026-88822](https://avd.aquasec.com/nvd/cve-2026-88822) | openssl | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Information disclosure via uninitialized memory in QUIC peer detection |
| low | [CVE-2026-88823](https://avd.aquasec.com/nvd/cve-2026-88823) | openssl | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Message authentication bypass via zero-length AAD update |
| low | [CVE-2026-88822](https://avd.aquasec.com/nvd/cve-2026-88822) | openssl-dev | 3.5.9-r0 | 3.5.9-r1 | openssl: openssl: Information disclosure via uninitialized memory in QUIC peer detection |
| low | [CVE-2026-88823](https://avd.aquasec.com/nvd/cve-2026-88823) | openssl-dev | 3.5.9-r0 | 3.5.9-r1 | openssl: OpenSSL: Message authentication bypass via zero-length AAD update |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-apache2 | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-bcmath | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-calendar | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-common | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-ctype | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-curl | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-gd | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-gmp | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-intl | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-mbstring | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-mysqli | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-mysqlnd | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-openssl | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-pdo | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-pdo_mysql | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-posix | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-session | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-simplexml | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-tokenizer | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-xml | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-xmlwriter | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| low | [CVE-2026-129506](https://avd.aquasec.com/nvd/cve-2026-129506) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: SOAP use-after-free when processing DTD entity references |
| low | [CVE-2026-15353](https://avd.aquasec.com/nvd/cve-2026-15353) | php85-zip | 8.5.12-r0 | 8.5.13-r0 | php: SOAP information disclosure through malformed array dimensions) |
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.58.0 | 0.58.1-0.20261009181718-2f1f834330b2 | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |

</details>
