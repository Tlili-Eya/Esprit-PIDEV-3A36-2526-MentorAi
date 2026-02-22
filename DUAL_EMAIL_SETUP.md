# 📧 Email Configuration - Mentor Platform

## ✅ Configuration Complete!

Your system is configured to send emails using **Amal's Gmail account**.

### 📬 Email Account

**Amal's Account**: `amal.mokdad07@gmail.com` ⭐ **ACTIVE**
- Used for: **All feedback notifications**
- Sends immediately (not queued)

---

## 🔧 Configuration Files

**`.env`** - Contains email credentials:
```dotenv
# Hejer's email (available but not currently used)
MAILER_DSN_HEJER="gmail+smtp://hejerh666@gmail.com:jntuuxtzckyvlgqk@default?verify_peer=0"

# Amal's email (currently active)
MAILER_DSN_AMAL=smtp://amal.mokdad07@gmail.com:pkcxaobvyouwctmk@smtp.gmail.com:587?encryption=tls&auth_mode=login
```

**`config/packages/mailer.yaml`** - Active configuration:
```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN_AMAL)%'
        envelope:
            sender: 'amal.mokdad07@gmail.com'
        headers:
            from: 'Mentor Platform <amal.mokdad07@gmail.com>'
```

**`config/packages/messenger.yaml`** - Emails send immediately:
```yaml
routing:
    Symfony\Component\Mailer\Messenger\SendEmailMessage: sync  # Not queued!
```

---

## 📨 Current Email Flow

### 1. User Submits Feedback
- **Sender**: `amal.mokdad07@gmail.com`
- **Receiver**: User's email (e.g., `amal.mokdad08@gmail.com`)
- **Subject**: "📬 Nous avons bien reçu votre feedback"
- **Template**: `templates/emails/feedback_received.html.twig`
- **Timing**: Sent immediately

### 2. Admin Responds to Feedback
- **Sender**: `amal.mokdad07@gmail.com`
- **Receiver**: User's email (e.g., `amal.mokdad08@gmail.com`)
- **Subject**: "✅ Your feedback has been answered"
- **Template**: `templates/emails/feedback_treated_simple.html.twig`
- **Timing**: Sent immediately

---

## 🔄 How to Switch to Hejer's Email

Edit `config/packages/mailer.yaml`:
```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN_HEJER)%'  # Change this line
        envelope:
            sender: 'hejerh666@gmail.com'  # Change this
        headers:
            from: 'Mentor Platform <hejerh666@gmail.com>'  # Change this
```

Then clear cache:
```bash
php bin/console cache:clear
```

---

## 🧪 Testing

### Test Current Setup (Amal's Email)
1. Log in as a user (e.g., `amal.mokdad08@gmail.com`)
2. Submit a feedback from `/contact`
3. Check inbox - should receive confirmation from `amal.mokdad07@gmail.com` immediately
4. Log in as admin and respond to the feedback
5. Check inbox again - should receive response notification immediately

---

## 📝 Important Notes

- ✅ Emails send **immediately** (not queued in database)
- ✅ Currently using Amal's account: `amal.mokdad07@gmail.com`
- ✅ Hejer's account available in `.env` if you want to switch
- ✅ Works with any recipient email address
- ✅ No database changes needed
- ✅ All feedback system emails use this configuration

---

## 🚨 Troubleshooting

### Email not received?
1. Check spam folder
2. Verify user has valid email in database:
   ```sql
   SELECT id, email, nom, prenom FROM utilisateur WHERE email IS NOT NULL;
   ```
3. Check Symfony logs: `var/log/dev.log`
4. Verify messenger is sending sync (not async):
   ```bash
   php bin/console debug:config framework messenger
   ```

### Switch back to Amal's email
Just change the `dsn` line in `config/packages/mailer.yaml` to:
```yaml
dsn: '%env(MAILER_DSN_AMAL)%'
```

---

## ✅ Status: READY TO USE!

Your email system is configured and working. All feedback emails will be sent from `amal.mokdad07@gmail.com` immediately upon submission or admin response.
