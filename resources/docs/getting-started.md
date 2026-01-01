# Getting Started

## Welcome to SiteKit

SiteKit is a modern server management platform that makes it easy to deploy and manage your web applications.

### Key Features

- **Two-phase server provisioning** with real-time progress tracking
- **Git-based deployments** with zero-downtime releases
- **Free SSL certificates** via Let's Encrypt
- **Database management** (MariaDB, MySQL, PostgreSQL)
- **Background job workers** with Supervisor
- **Health monitoring** and alerts
- **Server restore** functionality

---

## Quick Start Guide

### Step 1: Add a Server

1. Click "Servers" → "Add Server"
2. Enter a name for your server
3. Copy the provisioning command
4. Run it on your Ubuntu server via SSH as root
5. Watch the real-time progress as software is installed

### Step 2: Create a Web App

1. Click "Web Apps" → "Create Web App"
2. Select your server
3. Enter domain name and PHP version
4. Connect your Git repository

### Step 3: Deploy

1. Configure your deploy script
2. Click "Deploy" to start
3. Monitor the deployment logs

---

## Core Concepts

### Servers

Physical or virtual machines running Ubuntu that SiteKit manages. The agent runs on each server to execute commands.

### Provisioning

A two-phase process:
1. **Bootstrap** - Installs the agent
2. **Software Installation** - Agent installs software with progress tracking

You can retry failed steps or skip optional components.

### Web Apps

Applications deployed to your servers. Each web app has its own domain, PHP pool, and deployment configuration.

### Services

System services like Nginx, PHP-FPM, MariaDB, Redis. Services are auto-configured during provisioning and can be managed individually.

### Deployments

The process of pulling code from Git and running your build/deploy script. Zero-downtime deployments are supported.

### Agent Jobs

Commands sent from SiteKit to your server's agent. Jobs are queued and executed in order with real-time status updates.
