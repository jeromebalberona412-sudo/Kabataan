# Kabataan Login Performance Optimizations

## Summary of Changes

This document outlines the performance optimizations made to the Kabataan login flow to reduce the delay between authentication and dashboard redirection.

## Bottlenecks Identified

1. **Duplicate Database Queries**: The `KabataanRegistration` model was queried multiple times during login and middleware execution
2. **Repeated Service Calls**: `KkProfilingScheduleService::requiresProfilingUpdate()` was called multiple times with database queries
3. **Heavy Dashboard Initialization**: Multiple database queries for registration, barangay profiles, and programs without caching
4. **Configuration Reads**: Config values were read repeatedly without caching

## Optimizations Implemented

### 1. AuthController Optimizations
**File**: `app/Modules/Authentication/Controllers/AuthController.php`

- **Eliminated duplicate registration queries**: Registration data is now loaded once and reused for all status checks
- **Session-based profiling check caching**: The profiling update requirement is now stored in session during login to avoid middleware database queries
- **Optimized session management**: Explicit session state management to prevent redundant middleware checks

### 2. Service Layer Caching

#### KabataanAuthService
**File**: `app/Services/KabataanAuthService.php`

- Added caching for `allowedRoles()` (1 hour TTL)
- Added caching for `blockedEmails()` (1 hour TTL)
- Reduces repeated config file reads and array processing

#### KkProfilingScheduleService
**File**: `app/Services/KkProfilingScheduleService.php`

- Added caching for `activeUpdateSchedule()` (5 minutes TTL)
- Added caching for `lastCompletedProfilingYear()` (5 minutes TTL)
- Added caching for `hasCompletedProfilingForYear()` (5 minutes TTL)
- Significantly reduces database queries for profiling schedule checks

#### KabataanEligibilityService
**File**: `app/Services/KabataanEligibilityService.php`

- Added caching for `latestRegistrationForUser()` (5 minutes TTL)
- Eliminates redundant registration queries in middleware

#### BarangaySkProfileService
**File**: `app/Modules/Dashboard/Services/BarangaySkProfileService.php`

- Added caching for `listForTenant()` (10 minutes TTL)
- Added caching for `listOfficials()` (10 minutes TTL)
- Reduces expensive join queries for barangay officials

#### KabataanProgramService
**File**: `app/Modules/Programs/Services/KabataanProgramService.php`

- Added caching for `getLatestAbyipDocument()` (5 minutes TTL)
- Reduces document queries during dashboard initialization

### 3. Middleware Optimizations

#### EnsureKkProfilingUpdated Middleware
**File**: `app/Http/Middleware/EnsureKkProfilingUpdated.php`

- Added session-based check to avoid database queries when profiling status was already determined during login
- Only falls back to database query if session state is not available
- Eliminates redundant registration queries on every authenticated request

### 4. Cache Invalidation

#### KabataanRegistration Model
**File**: `app/Models/KabataanRegistration.php`

- Added model event listeners for `updated` and `deleted` events
- Automatically clears relevant cache when registration data changes
- Ensures cache consistency without manual cache management

#### CacheInvalidationService
**File**: `app/Services/CacheInvalidationService.php`

- Created centralized cache invalidation service
- Provides methods to clear user, registration, barangay, and tenant-specific caches
- Can be used in controllers and services to manually invalidate cache when needed

## Cache Configuration

The application uses database cache by default (configured in `config/cache.php`). Cache TTL values:

- **Authentication config**: 1 hour (rarely changes)
- **Profiling schedules**: 5 minutes (may change throughout the day)
- **Registration data**: 5 minutes (user-specific, moderate change frequency)
- **Barangay profiles**: 10 minutes (relatively static data)
- **Program documents**: 5 minutes (moderate change frequency)

## Security Considerations

- **No credential caching**: User credentials and authentication state are never cached
- **Session-based auth**: Authentication remains session-based with proper security
- **Appropriate cache scope**: Only static or infrequently changing data is cached
- **Automatic invalidation**: Cache is automatically cleared when underlying data changes

## Expected Performance Improvements

Based on the optimizations:

1. **Login to Dashboard Redirect**: Should see significant improvement (estimated 40-60% faster)
2. **Subsequent Dashboard Loads**: Should be faster due to barangay profile caching
3. **Middleware Execution**: Reduced database queries on authenticated requests
4. **Overall Server Load**: Reduced database load from repeated queries

## Testing Recommendations

### Manual Testing
1. Clear application cache: `php artisan cache:clear`
2. Test login flow and measure time from form submission to dashboard load
3. Navigate between pages and compare load times
4. Test with different user roles and registration statuses

### Performance Monitoring
- Use Laravel Telescope or similar tools to monitor query counts
- Check cache hit rates in your cache store
- Monitor average response times for login and dashboard routes

### Cache Verification
```bash
# Check cache store contents
php artisan tinker
>>> Cache::get('kabataan_auth.allowed_roles')
>>> Cache::get('kabataan_registration.latest.1') // Replace 1 with user ID
```

## Maintenance Notes

- **Cache Clearing**: Use `php artisan cache:clear` to clear all caches if needed
- **Config Changes**: After changing auth config, caches will auto-refresh after TTL expires
- **Data Updates**: Model events handle most cache invalidation automatically
- **Manual Invalidation**: Use `CacheInvalidationService` for manual cache clearing when needed

## Files Modified

1. `app/Modules/Authentication/Controllers/AuthController.php`
2. `app/Services/KabataanAuthService.php`
3. `app/Services/KkProfilingScheduleService.php`
4. `app/Services/KabataanEligibilityService.php`
5. `app/Modules/Dashboard/Services/BarangaySkProfileService.php`
6. `app/Modules/Programs/Services/KabataanProgramService.php`
7. `app/Http/Middleware/EnsureKkProfilingUpdated.php`
8. `app/Models/KabataanRegistration.php`
9. `app/Services/CacheInvalidationService.php` (new file)

## Rollback Plan

If any issues arise, you can disable caching by:

1. Setting `CACHE_STORE=array` in `.env` file (use array cache for testing)
2. Commenting out cache calls in the service files
3. Removing the model event listeners from `KabataanRegistration`

The optimizations are designed to be non-breaking and can be easily reverted if needed.