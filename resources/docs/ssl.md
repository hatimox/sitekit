# SSL Certificates

## Issuing Certificates

SiteKit automatically provisions free SSL certificates via Let's Encrypt.

**Requirements:**
- Domain must point to your server's IP address
- Port 80 must be accessible (for HTTP validation)
- Domain must be publicly accessible

**Automatic SSL:**
When you create a web app with a domain, SSL is configured automatically:
1. Nginx serves HTTP initially
2. Let's Encrypt validates domain ownership
3. Certificate is issued and installed
4. HTTPS is enabled with automatic redirect

**Issuing Manually:**
1. Go to Web App → SSL tab
2. Click "Issue Certificate"
3. Wait for validation to complete

**Wildcard Certificates:**
Wildcard certificates (`*.example.com`) require DNS validation:
1. Add a DNS TXT record as instructed
2. Wait for DNS propagation
3. Complete the validation

---

## Auto-Renewal

Certificates are renewed automatically before expiration.

**Renewal Process:**
- Certificates are checked daily
- Renewal starts 30 days before expiration
- Same validation method as initial issue
- Zero downtime during renewal

**Renewal Monitoring:**
- SiteKit tracks certificate expiration dates
- Alerts are sent if renewal fails
- Manual renewal available as backup

**If Renewal Fails:**
1. Check that domain still points to server
2. Verify port 80 is accessible
3. Check for DNS issues
4. Try issuing a new certificate manually

---

## Custom Certificates

Use your own certificates for enterprise or EV requirements.

**Supported Formats:**
- Certificate: PEM format (`.crt` or `.pem`)
- Private Key: PEM format (`.key`)
- Chain: Full certificate chain (optional)

**Installing Custom Certificate:**
1. Go to Web App → SSL tab
2. Select "Custom Certificate"
3. Paste your certificate, private key, and chain
4. Save and apply

**Certificate Format:**
```
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAJC1HiIAZAiUMA0Gcqgs...
-----END CERTIFICATE-----
```

**Key Format:**
```
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSk...
-----END PRIVATE KEY-----
```

**Notes:**
- Custom certificates don't auto-renew
- Set a reminder before expiration
- Keep private keys secure
