# Firewall

## Firewall Rules

Firewall rules control which traffic is allowed to reach your server.

**Rule Types:**
- **Allow** - Permits traffic matching the rule
- **Deny** - Blocks traffic matching the rule

**Default Rules:**
After provisioning, these ports are open:
- SSH (port 22) - Allow from any
- HTTP (port 80) - Allow from any
- HTTPS (port 443) - Allow from any

**Creating Rules:**
1. Navigate to "Firewall" → "Add Rule"
2. Enter a name for the rule
3. Select port or port range
4. Choose protocol (TCP, UDP, or both)
5. Specify source IP or CIDR range
6. Select action (Allow or Deny)

**Port Ranges:**
- Single port: `3306`
- Port range: `8000-8100`
- Common ports: `80,443`

**Best Practices:**
- Restrict SSH access to known IPs when possible
- Only open ports that your application needs
- Use specific IP ranges instead of "any" for sensitive services
- Review and audit rules regularly

---

## CIDR Notation

CIDR notation specifies IP address ranges.

**Format:** `IP_ADDRESS/PREFIX_LENGTH`

**Examples:**
| CIDR | Description | IP Count |
|------|-------------|----------|
| `192.168.1.1/32` | Single IP address | 1 |
| `192.168.1.0/24` | 192.168.1.0 - 192.168.1.255 | 256 |
| `192.168.0.0/16` | 192.168.0.0 - 192.168.255.255 | 65,536 |
| `10.0.0.0/8` | 10.0.0.0 - 10.255.255.255 | 16,777,216 |
| `0.0.0.0/0` | All IPv4 addresses (any) | All |

**Common Uses:**
- `/32` - Single server or IP
- `/24` - Office network (256 IPs)
- `/16` - Large corporate network
- `/0` - Open to entire internet

**Quick Reference:**
| Prefix | Addresses |
|--------|-----------|
| /32 | 1 |
| /31 | 2 |
| /30 | 4 |
| /29 | 8 |
| /28 | 16 |
| /27 | 32 |
| /26 | 64 |
| /25 | 128 |
| /24 | 256 |

---

## Security Recommendations

**SSH Access:**
- Restrict to your office IP or VPN
- Use key-based authentication (default)
- Consider changing default port

**Database Ports:**
- Keep 3306 (MySQL) and 5432 (PostgreSQL) blocked
- Use SSH tunnels for remote database access
- Only open if absolutely necessary, restrict to specific IPs

**Application Ports:**
- Only expose HTTP (80) and HTTPS (443) publicly
- Keep development ports (3000, 8000, etc.) restricted
- Use reverse proxy for internal services

**Monitoring:**
- Enable fail2ban (installed by default)
- Review auth logs regularly
- Set up alerts for failed login attempts
