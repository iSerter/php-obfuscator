# Docker Guide

The official Docker image lets you run `iserter/php-obfuscator` without installing PHP or Composer locally.

## Quick Start

```bash
docker run --rm -v $(pwd):/app iserter/php-obfuscator src/ -o out/
```

- `-v $(pwd):/app` mounts your project into the container's working directory.
- `src/` is the input directory (relative to `/app`).
- `-o out/` is the output directory.

All CLI flags work the same way as the local binary:

```bash
docker run --rm -v $(pwd):/app iserter/php-obfuscator src/ -o out/ --clean --force -c my-config.yaml
```

## Available Tags

| Tag      | Description                                |
|----------|--------------------------------------------|
| `latest` | Most recent stable release                 |
| `0.1.4`  | Pinned to a specific version               |

We recommend pinning to a version tag in CI pipelines to avoid surprises from new releases.

## Architecture

The image is published as a **multi-arch manifest** supporting:

| Platform       | Use case                                        |
|----------------|-------------------------------------------------|
| `linux/amd64`  | GitHub Actions runners, Intel/AMD servers & VMs |
| `linux/arm64`  | Apple Silicon (Docker Desktop), ARM servers     |

Docker automatically pulls the correct platform for your host. To force a specific platform:

```bash
docker run --rm --platform linux/amd64 -v $(pwd):/app iserter/php-obfuscator src/ -o out/
```

## Building from Source

Clone the repository and build the image locally:

```bash
git clone https://github.com/iserter/php-obfuscator.git
cd php-obfuscator
docker build -t my-obfuscator .
```

To build a multi-arch image and push to a registry:

```bash
docker buildx build \
  --platform linux/amd64,linux/arm64 \
  -t iserter/php-obfuscator:latest \
  --push .
```

> **Note:** Multi-arch builds with `--push` require a buildx builder that supports multiple platforms. Docker Desktop includes one by default (`desktop-linux`). On Linux servers, create one with `docker buildx create --use`.

## Base Image

The Docker image is based on `php:8.4-cli-alpine`, keeping it lightweight (~50 MB compressed). It includes only the runtime dependencies needed for the obfuscator (no Xdebug, no web server).
