# Migration & Setup Checklist

Use this checklist when setting up Pudo shipping for your Next.js/ERPNext site.

## 🎯 Phase 1: Initial Setup

### Next.js Environment
- [ ] Node.js 18+ installed
- [ ] Next.js 14+ project created
- [ ] Copy all files from this repository to your project
- [ ] Run `npm install` to install dependencies
- [ ] Copy `.env.example` to `.env.local`
- [ ] Verify TypeScript compiles: `npm run type-check`

### Pudo API Credentials
- [ ] Contact The Courier Guy for API access
- [ ] Request sandbox API key for testing
- [ ] Add sandbox key to `.env.local`
- [ ] Test sandbox connection: `curl http://localhost:3000/api/pudo/lockers`
- [ ] Request production API key (when ready)

## 📦 Phase 2: Product Data Setup

### If Using ERPNext
- [ ] Add custom fields to Item doctype (length, width, height)
- [ ] Bulk update existing items with dimensions
- [ ] Set up validation rules for dimensions
- [ ] Create item groups with default dimensions
- [ ] Test item dimension retrieval

### If Using Custom Database
- [ ] Ensure product table has dimension fields
- [ ] Populate dimensions for all products
- [ ] Create dimension validation logic
- [ ] Test data access from API

## 🔌 Phase 3: ERPNext Integration (If Applicable)

### Custom Fields Setup
- [ ] Create custom fields on Sales Order
- [ ] Create custom fields on Delivery Note
- [ ] Create custom fields on Item
- [ ] Create Pudo Settings single doctype
- [ ] Test field visibility and permissions

### Server Scripts
- [ ] Install rate calculation script (Sales Order - Before Save)
- [ ] Install booking creation script (Delivery Note - On Submit)
- [ ] Test scripts in development
- [ ] Add error handling and logging
- [ ] Test with various shipping methods

### API Configuration
- [ ] Set Next.js API URL in Pudo Settings
- [ ] Configure company default address
- [ ] Set default source locker (if using L2L/L2D)
- [ ] Test ERPNext → Next.js → Pudo API flow

## 🧪 Phase 4: Testing

### Unit Testing
- [ ] Test bin-packing with various item combinations
- [ ] Test all 4 shipping methods (L2L, D2L, L2D, D2D)
- [ ] Test with single item
- [ ] Test with multiple items
- [ ] Test with items that don't fit (should fail gracefully)
- [ ] Test with zero-dimension items

### Integration Testing
- [ ] Create test Sales Order in ERPNext
- [ ] Verify rates calculate correctly
- [ ] Compare rates with WooCommerce plugin (if migrating)
- [ ] Create test Delivery Note
- [ ] Verify booking creation works
- [ ] Download waybill PDF
- [ ] Download label PDF
- [ ] Verify tracking number generation

### Edge Cases
- [ ] Test with items exceeding max box size
- [ ] Test with invalid locker codes
- [ ] Test with missing dimensions
- [ ] Test with network timeout
- [ ] Test with invalid API key
- [ ] Test with empty cart

## 🚀 Phase 5: Production Preparation

### Configuration
- [ ] Switch to production API URL
- [ ] Update API key to production key
- [ ] Configure production Next.js domain
- [ ] Update ERPNext Pudo Settings with production URL
- [ ] Set up environment variables on hosting platform

### Security
- [ ] Verify API key is not in git repository
- [ ] Ensure .env.local is in .gitignore
- [ ] Set up environment variables in Vercel/hosting
- [ ] Review API route authentication
- [ ] Add rate limiting (optional but recommended)
- [ ] Set up error monitoring (Sentry, etc.)

### Performance
- [ ] Implement locker data caching
- [ ] Add request timeouts
- [ ] Test under load
- [ ] Optimize bin-packing for large carts
- [ ] Set up CDN for static assets

## 📊 Phase 6: Monitoring & Maintenance

### Logging
- [ ] Set up error logging
- [ ] Log failed rate calculations
- [ ] Log failed bookings
- [ ] Track API response times
- [ ] Monitor API usage/limits

### Documentation
- [ ] Document your company's shipping workflow
- [ ] Create user guide for staff
- [ ] Document locker selection process
- [ ] Create troubleshooting guide
- [ ] Document ERPNext custom fields

### Training
- [ ] Train staff on new system
- [ ] Document differences from old system
- [ ] Create video tutorials
- [ ] Set up support channel

## ✅ Final Checks

### Pre-Launch
- [ ] All tests passing
- [ ] Production API key working
- [ ] ERPNext integration tested end-to-end
- [ ] Waybill/label generation working
- [ ] Error handling in place
- [ ] Monitoring/logging configured
- [ ] Documentation complete
- [ ] Team trained

### Launch Day
- [ ] Switch to production configuration
- [ ] Monitor first few orders closely
- [ ] Verify bookings create successfully
- [ ] Check waybills print correctly
- [ ] Confirm tracking numbers work
- [ ] Be available for quick fixes

### Post-Launch (First Week)
- [ ] Monitor error logs daily
- [ ] Track booking success rate
- [ ] Gather user feedback
- [ ] Compare shipping costs with previous system
- [ ] Optimize any pain points

## 🔄 Migration from WooCommerce (If Applicable)

### Preparation
- [ ] Export WooCommerce Pudo settings
- [ ] Note API key and credentials
- [ ] Export product dimensions
- [ ] Document current shipping workflow
- [ ] Identify any customizations

### Parallel Running
- [ ] Keep WooCommerce active initially
- [ ] Run Next.js system in parallel
- [ ] Compare rates between systems
- [ ] Verify identical results
- [ ] Test edge cases

### Cutover
- [ ] Choose cutover date
- [ ] Inform customers of any changes
- [ ] Update shipping policies
- [ ] Switch ERPNext to use Next.js API
- [ ] Disable WooCommerce Pudo plugin
- [ ] Monitor for issues

## 📞 Support Contacts

- **Pudo API Issues:** The Courier Guy support
- **ERPNext Issues:** Frappe community / your ERPNext partner
- **Next.js Issues:** This repository issues
- **Integration Issues:** Your development team

## 📝 Notes Section

Use this space for your own notes during setup:

```
Date Started: _______________
Expected Completion: _______________
Team Members: _______________

Blockers:
- 
- 
- 

Completed Date: _______________
```

---

**Remember:** Test thoroughly in sandbox before going to production!

This checklist is designed to be printed and checked off as you progress through the setup and migration process.
