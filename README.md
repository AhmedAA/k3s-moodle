# Moodle GitOps Deployment

This repository contains the GitOps configuration for deploying and managing Moodle 5.3 across multiple k3s clusters. 

The architecture has been modernized from a legacy Bitnami Helm chart to a fully
declarative GitOps workflow using **Flux CD**. It uses a custom immutable Docker
image based on Aikido Security's hardened PHP 8.3 base to automatically patch
critical vulnerabilities (e.g., PHP2Shell), separating application code from
stateful data.

## Repository Structure

The repository uses Kustomize to manage multiple Moodle instances from a single base configuration.

```text
.
├── Containerfile                 # Custom immutable Moodle image definition
├── 99-custom.ini                 # Custom PHP settings baked into the image
├── infrastructure/               # Cluster-level configurations
│   ├── cluster-issuer.yml        # Let's Encrypt configuration
│   └── traefik-config.yml        # Hetzner Load Balancer proxy protocol setup
└── apps/
    └── moodle/
        ├── base/                 # Generic manifests shared across all instances
        │   ├── deployment.yml
        │   ├── service.yml
        │   ├── hpa.yml
        │   ├── ingress.yml
        │   ├── cronjob.yml
        │   └── pvc.yml           # 20Gi NFS claim for moodledata
        └── overlays/             # Instance-specific configurations
            ├── instance-iva/
            └── instance-maahad/
                ├── patch-ingress.yml   # Domain overrides
                └── kustomization.yml   # Merges base with patches
```

## Prerequisites (Fresh Installation)

* A k3s cluster with an operational Traefik ingress controller.
* An NFS server accessible by the k3s nodes.
* An external MariaDB server (11.4+).
* Flux CLI installed on your management machine/VPS.

```bash
curl -s https://fluxcd.io/install.sh | sudo bash
flux check --pre
```

## Deployment Workflow

### 1. Build and Push the Container Image

The application requires a custom image that bakes in Moodle core, plugins/themes (e.g., Academi), and PHP configurations.
You can trigger the `.github/workflows/build-and-push.yml` GitHub Action manually, or build and push it via CLI:

```bash
docker build -t ghcr.io/your-org/moodle-custom:latest -f Containerfile .
docker push ghcr.io/your-org/moodle-custom:latest
```

### 2. Apply Out-of-Band Secrets

To prevent "harvest now, decrypt later" attacks, database credentials and API
keys (e.g., Viva Wallet) are **not** tracked in this Git repository. They must
be provisioned manually before Flux deploys the application.

1. Create a local template based on your configuration requirements.
2. Apply it directly to the cluster:

```bash
nano real-secret.yml # Add your DB passwords and keys
kubectl apply -f real-secret.yml -n default
rm real-secret.yml
```

### 3. Bootstrap Flux

Install Flux on your cluster and configure it to watch this repository. You will need a GitHub Personal Access Token (PAT) with `repo` permissions.

```bash
export GITHUB_TOKEN=<your-token>
export GITHUB_USER=<your-username>

flux bootstrap github \
  --owner=$GITHUB_USER \
  --repository=<repo-name> \
  --branch=main \
  --path=./apps/moodle/overlays/instance-maahad \
  --personal \
  --token-auth
```

Flux will immediately begin deploying the resources. Any future commits to the `main` branch will automatically sync to the cluster.

---

## Migration from Legacy Bitnami Helm Deployments

If you are migrating an existing Moodle instance deployed via the Bitnami Helm
chart, you must safely transition your Persistent Volumes (PVs) before
destroying the Helm release. Bitnami bundled application code and `moodledata`
into a single volume; the new architecture only mounts `moodledata`.

**1. Protect the Persistent Volume**
Prevent Kubernetes from deleting your NFS data when the Helm chart is uninstalled.

```bash
# Identify your existing PV
kubectl get pvc -n default

# Patch the reclaim policy to Retain
kubectl patch pv <YOUR_PV_NAME> -p '{"spec":{"persistentVolumeReclaimPolicy":"Retain"}}'
```

**2. Realign NFS Data**
SSH into your NFS server. The new container image
strictly mounts `/var/www/moodledata`. You must move the contents of your
existing Bitnami `moodledata` folder to the root of the path expected by the new
GitOps PVC. The old Bitnami Moodle PHP core files can be archived or deleted, as
they are now baked into the Docker image.

**3. Uninstall the Legacy Chart**
Once the PV is secured and the out-of-band secrets are applied, remove the old release:

```bash
helm uninstall moodle -n default
```

After uninstallation, proceed with the standard Flux Bootstrap command to deploy the modernized architecture.
