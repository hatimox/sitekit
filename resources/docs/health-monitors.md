# Health Monitors

## Overview

Health monitors continuously check if your services are running and notify you when something goes wrong. SiteKit supports multiple monitor types for comprehensive uptime monitoring.

**Why use health monitors?**
- Get notified immediately when services go down
- Track uptime and response times
- Identify performance issues before users notice
- Monitor external dependencies

---

## Monitor Types

| Type | Description | Use Case |
|------|-------------|----------|
| **HTTP/HTTPS** | Checks URL responds with expected status | Websites, APIs, web apps |
| **TCP** | Checks if a port is accepting connections | Databases, custom services |
| **Ping** | ICMP ping check | Server availability |
| **Heartbeat** | Expects periodic pings from your app | Cron jobs, scheduled tasks |
| **SSL Expiry** | Monitors SSL certificate expiration | SSL certificate management |

---

## Creating a Monitor

**HTTP/HTTPS Monitor:**
1. Navigate to "Health Monitors" → "Create"
2. Select type: HTTP or HTTPS
3. Enter the URL to monitor (e.g., `https://example.com`)
4. Set check interval (default: 60 seconds)
5. Configure timeout and thresholds
6. Click "Create"

**Configuration Options:**
| Option | Description | Default |
|--------|-------------|---------|
| Interval | How often to check (seconds) | 60 |
| Timeout | Max wait time for response | 10s |
| Failure Threshold | Failures before marking down | 3 |
| Recovery Threshold | Successes before marking up | 2 |

---

## Monitor States

| State | Description |
|-------|-------------|
| **Up** | Service is responding normally |
| **Down** | Service has failed multiple checks |
| **Pending** | Monitor created, waiting for first check |
| **Paused** | Monitoring temporarily disabled |

**How state transitions work:**
- A service is marked **down** after `failure_threshold` consecutive failures
- A service is marked **up** after `recovery_threshold` consecutive successes
- This prevents false alarms from temporary network issues

---

## Notifications

When a monitor detects a problem, SiteKit sends notifications:

**Down Notification:**
Sent when a service transitions from up to down. Includes:
- Monitor name and URL
- Error message
- Time of failure

**Recovery Notification:**
Sent when a service comes back online. Includes:
- Monitor name
- Downtime duration
- Recovery time

**Notification Channels:**
Configure notification preferences in your user settings.

---

## Viewing Monitor Status

**Dashboard View:**
The health monitors list shows:
- Current status (up/down badge)
- Last check time
- Response time (for HTTP monitors)
- Consecutive failures/successes

**Monitor Details:**
Click on a monitor to see:
- Current status and history
- Response time trends
- Recent check results
- Error messages (if any)

---

## Best Practices

**Choosing check intervals:**
- Critical services: 30-60 seconds
- Standard services: 60-120 seconds
- Low priority: 300+ seconds

**Setting thresholds:**
- Use `failure_threshold: 3` to avoid false alarms
- Use `recovery_threshold: 2` to confirm recovery
- Adjust based on service reliability

**What to monitor:**
- Your main website/app URLs
- API health endpoints
- Node.js app health endpoints (e.g., `/health` or `/api/health`)
- Database connection (TCP)
- Critical third-party services
- SSL certificate expiry (30 days warning)

**Monitoring Node.js Apps:**
For Node.js applications, monitor the health endpoint configured in your app settings. Common patterns:
- Next.js: `/api/health`
- NestJS: `/health`
- Express: `/health` or `/api/health`

Example health endpoint (Express):
```javascript
app.get('/health', (req, res) => {
  res.json({ status: 'ok', uptime: process.uptime() });
});
```

**Monitor your monitors:**
- Check that notifications are being received
- Test with intentionally failing monitors
- Review monitor performance regularly
