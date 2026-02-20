# Account Creation and Wallet Setup Guide

This guide walks through signup, wallet configuration, and tier progression.

## Signup

1. Go to the signup page.
2. Enter your company name, work email, region, and industry.
3. Submit the form.

### Approval behavior

- Business domains are approved automatically.
- Consumer domains (gmail.com, yahoo.com) are queued for manual approval.

## Data Region

Choose a region at signup:

- US: Data stored in the US region.
- EU: Data stored in the EU region.

Region is immutable after signup.

## Apple Wallet setup

1. Download a CSR from the Apple setup page.
2. Log in to Apple Developer Portal and create a Pass Type certificate.
3. Upload the `.cer` file in the Apple setup page.
4. Verify the certificate shows a valid expiry date.

### Renewal

- Renew certificates before expiry.
- You will receive email reminders at 30, 7, and 0 days before expiry.

## Google Wallet setup

1. Create a Google Cloud project.
2. Enable the Google Wallet API.
3. Create a Service Account and download the JSON key.
4. Upload the JSON key in the Google setup page.

## Tier progression

- Email Verified: Default after signup and email approval.
- Verified and Configured: Requires Apple and Google credentials.
- Production: Request approval after configuration.
- Live: Requires pre-launch checklist completion.

## Onboarding wizard

The onboarding wizard appears on first login and tracks:

- Email verification
- Apple setup
- Google setup
- Profile completion
- First pass creation

The wizard auto-dismisses when all steps are complete.

## Troubleshooting

- Certificate upload errors: Confirm file type and validity dates.
- Google JSON errors: Ensure the key is a Service Account and not OAuth.
- Approval delays: Contact support if approval exceeds 24 hours.
