# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-11-16 13:20 UTC

Main at: `48289e0`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 1 |
| JavaScript · vue/package-lock.json | 0 | 0 | 2 | 1 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 1 | 0 | 4 | 0 | 13 |

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

<details><summary>1 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| low | [GHSA-gcqx-w48m-w2h4](https://github.com/advisories/GHSA-gcqx-w48m-w2h4) | deepmerge | 4.3.1 | not >=4.2.0 <=4.3.1 | deepmerge mergeObject() allows prototype poisoning of returned objects |

</details>

## Image · 4spaces/kubernetes-service-orchestrator:dev

*built from 48289e0, the commit above · alpine 3.24.4 · 4spaces/kubernetes-service-orchestrator@sha256:47c41427201a36e15e163d0583f1aa3b3f8f660db24edd74070d69ce2ae59b5e*

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| critical | [CVE-2026-119618](https://avd.aquasec.com/nvd/cve-2026-119618) | apache2 | 2.4.69-r0 | 2.4.71-r0 | httpd:2.4/mod_http2: mod_http2: httpd: Remote code execution in mod_http2 |
| medium | [CVE-2026-106023](https://avd.aquasec.com/nvd/cve-2026-106023) | apache2 | 2.4.69-r0 | 2.4.71-r0 | NULL pointer dereference vulnerability in Apache HTTP Server's mod_md  ... |
| medium | [CVE-2026-109163](https://avd.aquasec.com/nvd/cve-2026-109163) | apache2 | 2.4.69-r0 | 2.4.71-r0 | httpd: httpd: Denial of Service via stack-based buffer overflow in mod_vhost_alias |
| medium | [CVE-2026-128085](https://avd.aquasec.com/nvd/cve-2026-128085) | go.opentelemetry.io/otel/sdk | v1.46.0 | 1.47.0 | go.opentelemetry.io/otel/sdk: go.opentelemetry.io/otel/sdk/log: go.opentelemetry.io/otel/trace: OpenTelemetry-Go: Denia… |
| medium | [CVE-2026-128085](https://avd.aquasec.com/nvd/cve-2026-128085) | go.opentelemetry.io/otel/trace | v1.46.0 | 1.47.0 | go.opentelemetry.io/otel/sdk: go.opentelemetry.io/otel/sdk/log: go.opentelemetry.io/otel/trace: OpenTelemetry-Go: Denia… |

<details><summary>13 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| unknown | [CVE-2026-97033](https://avd.aquasec.com/nvd/cve-2026-97033) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package that uses cgo could cause arbitrary code  ... |
| unknown | [CVE-2026-97034](https://avd.aquasec.com/nvd/cve-2026-97034) | go | 1.26.10-r0 | 1.26.11-r0 | When using GCC, building a malicious package that uses cgo could cause ... |
| unknown | [CVE-2026-97035](https://avd.aquasec.com/nvd/cve-2026-97035) | go | 1.26.10-r0 | 1.26.11-r0 | When using Clang or GCC, building a malicious package that uses cgo co ... |
| unknown | [CVE-2026-97037](https://avd.aquasec.com/nvd/cve-2026-97037) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package that uses cgo could cause arbitrary code  ... |
| unknown | [CVE-2026-97038](https://avd.aquasec.com/nvd/cve-2026-97038) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package containing ".syso" files or cgo linker in ... |
| unknown | [CVE-2026-97039](https://avd.aquasec.com/nvd/cve-2026-97039) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package that uses "#cgo pkg-config:" directives c ... |
| unknown | [CVE-2026-97040](https://avd.aquasec.com/nvd/cve-2026-97040) | go | 1.26.10-r0 | 1.26.11-r0 | Building a package containing a crafted ".syso" file could bypass cgo  ... |
| unknown | [CVE-2026-97041](https://avd.aquasec.com/nvd/cve-2026-97041) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package containing "//go:cgo_*" compiler directiv ... |
| unknown | [CVE-2026-97042](https://avd.aquasec.com/nvd/cve-2026-97042) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package that uses "//go:cgo_import_dynamic" or li ... |
| unknown | [CVE-2026-97043](https://avd.aquasec.com/nvd/cve-2026-97043) | go | 1.26.10-r0 | 1.26.11-r0 | Building a malicious package that uses cgo could bypass "#cgo LDFLAGS" ... |
| unknown | [CVE-2026-97054](https://avd.aquasec.com/nvd/cve-2026-97054) | go | 1.26.10-r0 | 1.26.11-r0 | For HTTP/1 servers, when MaxBytesReader's limit is exceeded, "Connecti ... |
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.58.0 | 0.58.1-0.20261009181718-2f1f834330b2 | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |
| unknown | [CVE-2026-97054](https://avd.aquasec.com/nvd/cve-2026-97054) | stdlib | v1.27.3 | 1.26.11, 1.27.4 | For HTTP/1 servers, when MaxBytesReader's limit is exceeded, "Connecti ... |

</details>
