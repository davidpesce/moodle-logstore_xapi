# Security Policy

Thank you for helping keep `moodle-logstore_xapi` and its users safe. This document explains how to report security vulnerabilities in this plugin.

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues, discussions, or pull requests.**

Instead, please report them privately through one of the following channels:

1. **Preferred:** Use GitHub's private vulnerability reporting by clicking **"Report a vulnerability"** on the [Security Advisories page](https://github.com/davidpesce/moodle-logstore_xapi/security/advisories) for this repository.
2. **Alternative:** Email **support@exputo.com** with a description of the issue.

When reporting, please include as much of the following as you can:

- A description of the vulnerability and its potential impact
- Steps to reproduce, including a proof of concept if possible
- The version(s) of the plugin affected
- The Moodle version and PHP version you tested against
- Your name or handle for credit in the advisory (optional)

## Response Expectations

- We will acknowledge receipt of your report within **5 business days**.
- We will provide an initial assessment, including whether the report is accepted, within **10 business days**.
- We aim to release a fix for accepted reports within **15 days** of acknowledgment, depending on complexity and coordination with the Moodle security team where applicable.

We will keep you informed throughout the process and credit you in the published advisory unless you prefer to remain anonymous.

## Supported Versions

Security fixes are provided for the current major release line. Older versions may receive fixes on a best-effort basis if they correspond to a currently supported Moodle LTS.

## Scope

**In scope:**

- Vulnerabilities in the plugin code in this repository
- Vulnerabilities introduced by this plugin's interaction with Moodle APIs or with a configured LRS endpoint (e.g., SSRF, credential leakage, injection)
- Authentication, authorization, or data exposure issues in plugin-provided endpoints

**Out of scope:**

- Vulnerabilities in Moodle core — please report these to the [Moodle security team](https://moodledev.io/general/development/process/security)
- Vulnerabilities in a specific Learning Record Store (LRS) the plugin is configured to send to — please report to the LRS vendor
- Vulnerabilities in bundled third-party libraries (see [`thirdpartylibs.xml`](./thirdpartylibs.xml) and `composer.lock`) — please report to the upstream project. We still appreciate a heads-up so we can update the dependency.
- Findings from automated scanners without a demonstrated exploit
- Social engineering, physical attacks, or attacks requiring privileged access already granted by a Moodle administrator

## Coordinated Disclosure

We follow coordinated disclosure practices. We ask that you give us a reasonable opportunity to investigate and patch before disclosing publicly. Once a fix is released, we will publish a GitHub Security Advisory and, where appropriate, request a CVE.

## Recognition

We are grateful to the security researchers who help improve this plugin. Reporters of valid vulnerabilities will be credited in the published advisory unless they request otherwise. We do not currently offer a monetary bug bounty.
