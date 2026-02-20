# Pass Distribution — User Guide

## Overview

Pass Distribution lets you share your digital passes (Apple Wallet / Google Wallet) with recipients via unique, trackable links. Each link contains a QR code that recipients can scan or tap to add the pass to their mobile wallet.

## Creating a Distribution Link

1. Navigate to **Dashboard → Passes** and select the pass you want to distribute.
2. In the **Distribution** panel, click **Create Distribution Link**.
3. A new link is generated with a unique slug and `active` status.
4. Copy the shareable URL (e.g., `https://yourapp.com/p/abc123-def456`) or download the QR code.

## Sharing the Link

- **Direct link**: Send the URL via email, SMS, or messaging app.
- **QR code**: Print or display the QR code. Recipients scan it with their phone camera.
- **Embed**: Use the link in your website, flyer, or marketing material.

## Recipient Experience

When a recipient opens the link:

| Device | Behavior |
|--------|----------|
| **iPhone / iPad** | Shows an "Add to Apple Wallet" button. Tapping downloads the `.pkpass` file. |
| **Android** | Shows a "Save to Google Wallet" button linking to the Google save URL. |
| **Desktop / Other** | Shows the pass details and QR code for later mobile scanning. |

## Managing Links

### View All Links

Go to **Dashboard → Passes → {Pass} → Distribution Links** to see all links for that pass, including:

- **Slug** — the unique identifier in the URL
- **Status** — `active` or `disabled`
- **Access count** — how many times the link was opened
- **Last accessed** — when the link was last viewed

### Disable a Link

To stop a link from being accessed:

1. Find the link in the distribution panel.
2. Click the **Disable** button (or toggle status to `disabled`).
3. Recipients who visit the link will see a **403 Forbidden** error.

### Re-enable a Link

1. Find the disabled link.
2. Click **Enable** (or toggle status back to `active`).
3. The link is immediately accessible again.

## Pass Status Effects

| Pass Status | Link Behavior |
|-------------|--------------|
| **Active** | Link works normally — recipients can add the pass to their wallet. |
| **Expired** | Link still accessible, but shows an expiry message. Enrollment is blocked. |
| **Voided** | Link returns **410 Gone**. The pass is no longer available. |

## Access Tracking

Every time someone opens a distribution link:

- The **access count** increments by 1.
- The **last accessed** timestamp updates.

Use this data to measure engagement and identify which links are performing well.

## Tips

- Create **multiple links** per pass for different channels (email vs. print vs. social) to track which channel drives the most installs.
- **Disable** links after an event or promotion ends to prevent late enrollments.
- Check **access count** to gauge interest before a campaign ends.
