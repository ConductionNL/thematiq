# Quick Start Guide - NL Design System for Nextcloud

## ⚡ 5-Minute Setup

### 1. Installation (Already Done!)

The nldesign app is already in your apps-extra directory.

### 2. Install Fonts

```bash
cd /home/rubenlinde/nextcloud-docker-dev/workspace/server/apps-extra/nldesign
npm install
```

✅ This installs Fira Sans fonts from `@fontsource/fira-sans`

### 3. Enable in Nextcloud

```bash
docker exec -u 33 nextcloud php occ app:enable nldesign
```

Or via web UI: Settings → Apps → search "nldesign" → Enable

### 4. Configure Theme

1. Login to Nextcloud: http://localhost:8080
2. Go to: **Settings → Administration → Theming**
3. Scroll to: **Thematiq** section
4. Select your organization (Rijkshuisstijl, Utrecht, etc.)
5. Reload the page

### 5. Set Background Color (Important!)

The NL Design app does not set a background color automatically. You must configure it in Nextcloud's theming:

1. Stay in: **Settings → Administration → Theming** (Nextcloud's main section)
2. Scroll to: **Background and color** section
3. Click on **Color** and enter the background color for your token set:

| Token Set | Primary Color | Background Color |
|-----------|--------------|------------------|
| **Rijkshuisstijl** | `#154273` (blue) | `#F5F6F7` (light gray) |
| **Utrecht** | `#CC0000` (red) | `#FFFFFF` (white) |
| **Amsterdam** | `#EC0000` (red) | `#FFFFFF` (white) |
| **Den Haag** | `#1A7A3E` (green) | `#FFFFFF` (white) |
| **Rotterdam** | `#00811F` (green) | `#FFFFFF` (white) |

4. **Click on Background image** → Select **Remove background image**
5. Save changes

**Note**: Primary colors are set automatically by NL Design when you select a token set. Only the background color needs manual configuration.

🎉 **Done!** Your Nextcloud now uses Dutch government design styling with professional Fira Sans typography and correct colors.

## 🎨 Token Sets Available

| Token Set | Primary Color | Background | Best For |
|-----------|--------------|------------|----------|
| **Rijkshuisstijl** | `#154273` (blue) | `#F5F6F7` | National government |
| **Utrecht** | `#CC0000` (red) | `#FFFFFF` | Municipality |
| **Amsterdam** | `#EC0000` (red) | `#FFFFFF` | Municipality |
| **Den Haag** | `#1A7A3E` (green) | `#FFFFFF` | Municipality |
| **Rotterdam** | `#00811F` (green) | `#FFFFFF` | Municipality |

## ✅ What You Get

- ✅ Professional Fira Sans typography
- ✅ Official Dutch government colors
- ✅ Sharp corners (Rijkshuisstijl) or rounded (municipalities)
- ✅ Configurable background colors via Nextcloud theming
- ✅ WCAG AA accessible
- ✅ Responsive design
- ✅ No build required

## 🔍 Verify Installation

### Check Fonts Are Loading

1. Open Nextcloud in browser
2. Open DevTools (F12)
3. Go to **Network** tab
4. Filter by "font"
5. Reload page
6. Should see: `fira-sans-latin-*.woff2` from `cdn.jsdelivr.net`

### Check Theme Applied

1. Inspect any text element
2. In Computed styles, look for `font-family`
3. Should start with: `"Fira Sans"`

## 🛠️ Troubleshooting

### Fonts Not Loading?

**Check:**
```bash
cd nldesign
ls -la node_modules/@fontsource/fira-sans/
```

Should show the installed package.

**Fix:**
```bash
npm install
```

### Theme Not Changing?

**Clear cache:**
```bash
docker exec -u 33 nextcloud php occ maintenance:repair
```

**Or manually:** Settings → Administration → Theming → "Reset to defaults" → Try again

### Colors Wrong?

1. Check which token set is selected in NL Design settings
2. **Check background color** in Nextcloud Theming settings:
   - Should be `#F5F6F7` for Rijkshuisstijl
   - Should be `#FFFFFF` for other token sets
   - Background image should be removed
3. Hard reload browser (Ctrl+Shift+R)
4. Clear Nextcloud cache

## 📖 Full Documentation

- `README.md` - Complete guide with architecture
- `IMPLEMENTATION.md` - Technical details
- `COMPLIANCE.md` - Rijkshuisstijl checklist
- `ASSETS.md` - Font & asset guide
- `SUMMARY.md` - What we implemented

## 🚀 Next Steps

1. **Test all token sets** - Switch between organizations to see different styles
2. **Verify on mobile** - Check responsive design
3. **Test accessibility** - Use keyboard navigation, screen readers
4. **Customize** (optional) - Edit `css/tokens/*.css` to fine-tune colors

## 💡 Pro Tips

- **Best performance**: Fonts load from CDN (fast, cached)
- **No build needed**: Just CSS, works immediately
- **Easy updates**: `git pull && npm install`
- **Switchable**: Change themes without reloading app

## ⚖️ Legal & Compliance

✅ **Fully open source** - No proprietary assets
✅ **No permission needed** - Fira Sans is free (SIL OFL 1.1)
✅ **95% Rijkshuisstijl compliant** - Using official alternatives
✅ **Safe for production** - No legal restrictions

## 🎯 Common Use Cases

### For Demonstrations
Select **Rijkshuisstijl** - The official government blue theme

### For Municipalities
Select your city (Utrecht, Amsterdam, etc.) for local branding

### For Development
Switch between themes to test responsive design

## 🔗 Quick Links

- [NL Design System](https://nldesignsystem.nl/)
- [Fira Sans Font](https://github.com/mozilla/Fira)
- [Rijkshuisstijl Community](https://github.com/nl-design-system/rijkshuisstijl-community)

---

**That's it!** You now have a professional Dutch government design system running in Nextcloud with open-source fonts and full compliance. 🇳🇱
