# Security status

Known vulnerabilities in **main**: its lock files, and the `:dev` image built from it,
found by `composer audit`, `npm audit` and [Trivy](https://trivy.dev). A release carries
this file as it was when it was tagged. Written every week by
[a scheduled workflow](.github/workflows/security-status.yml) - do not edit by hand.

Updated: 2026-11-09 13:35 UTC

Main at: `ee35b3a`

| Source | Critical | High | Medium | Low | Unknown |
|--------|---------:|-----:|-------:|----:|--------:|
| PHP · ci4/composer.lock | 0 | 0 | 0 | 0 | 0 |
| JavaScript · vue/package-lock.json | 0 | 0 | 2 | 1 | 0 |
| Image · 4spaces/kubernetes-service-orchestrator:dev | 0 | 0 | 0 | 0 | 1 |

## PHP · ci4/composer.lock

*abandoned: vierbergenlars/php-semver*

Nothing known.

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

*built from ee35b3a, the commit above · alpine 3.24.4 · 4spaces/kubernetes-service-orchestrator@sha256:fa8a7de4d862bb1995053d43c107952e3cf8295738e25b5b4e0e419c4fdcc70c*


<details><summary>1 low or unknown</summary>

| Severity | Id | Package | Installed | Fix | Title |
|----------|----|---------|-----------|-----|-------|
| unknown | GO-2026-5932 | golang.org/x/crypto | v0.58.0 | 0.58.1-0.20261009181718-2f1f834330b2 | The golang.org/x/crypto/openpgp package is unmaintained, unsafe by design, and has known security issues |

</details>
