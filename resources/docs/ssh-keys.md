# SSH Keys

## Overview

SSH keys provide secure, password-less access to your servers. SiteKit makes it easy to manage SSH keys across all your servers with support for multiple users.

**Why use SSH keys?**
- More secure than passwords
- No password prompts when connecting
- Easy to revoke access by removing the key
- Can be shared across team members

---

## Adding an SSH Key

**Steps to add a new SSH key:**
1. Navigate to "SSH Keys" in the sidebar
2. Click "Create SSH Key"
3. Enter a descriptive name (e.g., "MacBook Pro", "CI/CD Server")
4. Paste your public key (starts with `ssh-rsa`, `ssh-ed25519`, etc.)
5. Optionally select servers to deploy to immediately
6. Click "Create"

**Generating a new SSH key:**
```bash
# Ed25519 (recommended - more secure, shorter)
ssh-keygen -t ed25519 -C "your-email@example.com"

# RSA (widely compatible)
ssh-keygen -t rsa -b 4096 -C "your-email@example.com"
```

Your public key is located at:
- `~/.ssh/id_ed25519.pub` (Ed25519)
- `~/.ssh/id_rsa.pub` (RSA)

---

## Deploying Keys to Servers

When deploying an SSH key to a server, you can choose which user to add it to:

**Target Users:**
| User | Path | Use Case |
|------|------|----------|
| `sitekit` | `/home/sitekit/.ssh/authorized_keys` | Recommended for most operations |
| `root` | `/root/.ssh/authorized_keys` | Full system access (use carefully) |

**Deploy to sitekit (Recommended):**
- Access to web apps and deployments
- Can run sudo commands when needed
- Safer than root access

**Deploy to root:**
- Full system administrator access
- Required for some system-level operations
- Use only when necessary

**Deploying an existing key:**
1. Go to SSH Keys list
2. Click the "Deploy" action on the key
3. Select target server(s)
4. Choose target user (sitekit or root)
5. Click "Deploy"

---

## Connecting to Your Server

Once your key is deployed, connect via SSH:

```bash
# Connect as sitekit user (recommended)
ssh sitekit@your-server-ip

# Connect as root
ssh root@your-server-ip
```

**SSH config for easier access:**
Add to `~/.ssh/config`:
```
Host myserver
    HostName your-server-ip
    User sitekit
    IdentityFile ~/.ssh/id_ed25519
```

Then connect with: `ssh myserver`

---

## Managing Keys

**Viewing deployed keys:**
Each SSH key shows which servers it's deployed to and which user it's associated with.

**Removing a key from a server:**
1. Find the key in the SSH Keys list
2. Click "Remove from Server" action
3. Select the server to remove from
4. Confirm removal

**Deleting a key entirely:**
1. First remove the key from all servers
2. Then delete the key from SiteKit

**Best Practices:**
- Use descriptive names for keys
- Use Ed25519 keys when possible
- Deploy to `sitekit` user unless root is required
- Regularly audit which keys have access
- Remove keys when team members leave
