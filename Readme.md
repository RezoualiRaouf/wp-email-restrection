# WP Email Restriction

**Contributors:** 

 - [Raouf Rezouali](https://github.com/RezoualiRaouf) 
 - [Samy Bacha](https://github.com/magnetarstar-hub) 
 - [Dany Yakoubi](https://github.com/daaaaaaanyyyyy) 


**Tags:** access control, email restriction, domain restriction, login, security  
**Requires at least:** WordPress 5.0  
**Tested up to:** WordPress 6.4  
**Requires PHP:** 7.4  
**License:** MIT 
 
Restrict website access or registrations to users with specific email domains. Ideal for corporate intranets, private communities, educational sites, or client portals.

---

## Description

WP Email Restriction allows site owners to limit access and registrations to specific email domains. After configuration, only users with authorized email addresses can register or log in — perfect for:

- **Corporate Intranets** – Employees only  
- **Private Communities** – Members-only environment  
- **Educational Sites** – Users with institution emails  
- **Client Portals** – Exclusive access for clients  

---

## Features

### 🔒 Domain-Based Access Control  
- Whitelist allowed email domain(s) (e.g., `@company.com`)  
- Validates email addresses during login or registration  

### 🧑 User Flow  
- Domains configured under **Settings → Email Restriction**  
- Only whitelisted emails can register/log in  
- Default WordPress admin users (`manage_options` capability) bypass restriction  

### ⚙️ Simple Management  
- Clean admin interface for domain configuration  
- Clear error messaging on denied access  

### 🔧 Developer-Friendly  
- Hooks and filters for customization  
- Extendable via themes or other plugins  

### 🔒 Security Standards  
- Built on WordPress’s authentication and nonce systems  
- Data sanitized and validated against approved domains  

---

## Installation

### 1. Automatic Installation  (not available yet!)
1. Go to **Plugins → Add New**  
2. Search for **WP Email Restriction**  
3. Click **Install Now**, then **Activate**

### 2. Manual Installation
1. Download the plugin ZIP or clone the epo
2. Upload folder to `/wp-content/plugins/`  
3. Activate via **Plugins** menu  


---

## Setup Guide

1. After activation, go to **Settings → Email Restriction**  
2. Add your domain (without `@`)  
3. Save changes  
4. Restriction applies to both registration and login  

---

## Frequently Asked Questions

**Q: Can I add multiple domains?**  
A: Yes — add each domain separated by commas.

**Q: What about existing users?**  
A: Current admin users remain unaffected. Others must match a whitelisted domain.

**Q: Can I customize error messages?**  
A: Yes — use the `wp_email_restriction_error_message` filter.

**Q: Does it work with multisite?**  
A: Not yet. Multisite support may be added in future versions.

---


## Support

Report issues or suggest features at the [GitHub repo issues page](https://github.com/RezoualiRaouf/wp-email-restrection/issues).

---

## License

This plugin is licensed under the **MIT License**
Do whatever you want, just don’t remove the original license and don’t blame us if something breaks.
