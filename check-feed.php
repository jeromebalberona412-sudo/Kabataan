<?php

use App\Models\Announcement;
use App\Models\KabataanRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Community Feed Diagnostic Script
 * Run with: php check-feed.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n╔══════════════════════════════════════════════════════╗\n";
echo "║     Community Feed Integration Diagnostic Tool      ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

// 1. Check if community_feeds table exists
echo "📊 [1/7] Checking if 'community_feeds' table exists...\n";
$tableExists = Schema::hasTable('community_feeds');
echo '   Result: '.($tableExists ? '✅ YES' : '❌ NO')."\n\n";

if (! $tableExists) {
    echo "   ❌ ERROR: Shared community_feeds table doesn't exist!\n";
    echo "   💡 FIX: Community Feed posts live in community_feeds, not announcements.\n\n";
    exit(1);
}

// 2. Count total posts
echo "📝 [2/7] Counting total posts in database...\n";
try {
    $totalPosts = Announcement::count();
    echo "   Result: ✅ $totalPosts posts found\n\n";

    if ($totalPosts === 0) {
        echo "   ⚠️  WARNING: No posts exist yet!\n";
        echo "   💡 TIP: Create test posts in SK Federation or SK Officials\n\n";
    }
} catch (Exception $e) {
    echo '   ❌ ERROR: '.$e->getMessage()."\n\n";
    exit(1);
}

// 3. Count federation-wide posts
echo "🌐 [3/7] Checking federation-wide posts...\n";
try {
    $fedPosts = Announcement::whereRaw('"is_federation_wide" = true')->count();
    echo "   Result: ✅ $fedPosts federation-wide posts\n\n";
} catch (Exception $e) {
    echo '   ❌ ERROR: '.$e->getMessage()."\n\n";
}

// 4. Posts by barangay
echo "🏘️  [4/7] Analyzing posts by barangay...\n";
try {
    $byBarangay = Announcement::selectRaw('barangay_id, COUNT(*) as count')
        ->whereNotNull('barangay_id')
        ->groupBy('barangay_id')
        ->get();

    if ($byBarangay->count() > 0) {
        echo '   Result: ✅ Found posts in '.$byBarangay->count()." barangays\n";
        foreach ($byBarangay as $group) {
            echo "      → Barangay ID {$group->barangay_id}: {$group->count} post(s)\n";
        }
        echo "\n";
    } else {
        echo "   Result: ⚠️  No barangay-specific posts found\n\n";
    }
} catch (Exception $e) {
    echo '   ❌ ERROR: '.$e->getMessage()."\n\n";
}

// 5. Check sample user
echo "👤 [5/7] Checking sample Kabataan user...\n";
try {
    $user = User::first();

    if (! $user) {
        echo "   ⚠️  WARNING: No users found in database\n\n";
    } else {
        echo "   User ID: {$user->id}\n";
        echo "   Name: {$user->name}\n";
        echo "   Email: {$user->email}\n";

        // Check user's barangay
        $registration = KabataanRegistration::where('user_id', $user->id)->latest()->first();
        $barangayId = $registration?->barangay_id ?? $user->barangay_id;

        echo '   User barangay_id: '.($barangayId ?? 'NULL')."\n";
        echo '   Registration barangay_id: '.($registration?->barangay_id ?? 'NULL')."\n";

        if (! $barangayId) {
            echo "   ❌ ERROR: User has NO barangay assigned!\n";
            echo "   💡 FIX: Assign barangay_id in users or kabataan_registrations table\n\n";
        } else {
            echo "   ✅ User has valid barangay\n\n";

            // 6. Query posts for this user
            echo "🔍 [6/7] Querying posts visible to this user...\n";
            $userPosts = Announcement::where(function ($q) use ($barangayId) {
                $q->where('barangay_id', $barangayId)
                    ->orWhereRaw('"is_federation_wide" = true');
            })
                ->where(function ($q) {
                    $q->whereRaw('"is_archived" = false')
                        ->orWhereNull('is_archived');
                })
                ->orderByDesc('created_at')
                ->get();

            echo "   Result: ✅ {$userPosts->count()} post(s) should be visible\n\n";

            if ($userPosts->count() > 0) {
                echo "   📋 Sample posts:\n";
                foreach ($userPosts->take(5) as $post) {
                    $source = $post->is_federation_wide ? '[SK Federation]' : "[Brgy {$post->barangay_id}]";
                    echo "      → ID {$post->id}: {$source} {$post->title}\n";
                }
                echo "\n";
            } else {
                echo "   ⚠️  No posts match this user's barangay!\n";
                echo "   💡 TIP: Create posts in SK Federation (federation-wide)\n";
                echo "         OR create posts in SK Officials for Barangay ID: $barangayId\n\n";
            }
        }
    }
} catch (Exception $e) {
    echo '   ❌ ERROR: '.$e->getMessage()."\n\n";
}

// 7. Check API endpoint
echo "🔌 [7/7] Testing API endpoint availability...\n";
try {
    $routes = Route::getRoutes();
    $feedRouteExists = false;

    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'api/feed') && in_array('GET', $route->methods())) {
            $feedRouteExists = true;
            break;
        }
    }

    if ($feedRouteExists) {
        echo "   Result: ✅ /api/feed route is registered\n";
        echo "   URL: http://10.239.121.87:8002/api/feed\n\n";
    } else {
        echo "   ❌ ERROR: /api/feed route NOT found!\n";
        echo "   💡 FIX: Check routes in Dashboard module\n\n";
    }
} catch (Exception $e) {
    echo '   ❌ ERROR: '.$e->getMessage()."\n\n";
}

// Summary
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║                   Summary                            ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";

if ($tableExists && $totalPosts > 0) {
    echo "✅ Database: OK - Table exists with $totalPosts posts\n";
} elseif ($tableExists && $totalPosts === 0) {
    echo "⚠️  Database: Table exists but NO posts yet\n";
    echo "   💡 ACTION: Create test posts in SK Federation/Officials\n";
} else {
    echo "❌ Database: Table missing\n";
    echo "   💡 ACTION: Run migrations\n";
}

echo "\n";
echo "Next Steps:\n";
echo "1. If no posts exist, create them in SK Federation or SK Officials\n";
echo "2. Check browser console (F12) for JavaScript errors\n";
echo "3. Test API: http://10.239.121.87:8002/api/feed (must be logged in)\n";
echo "4. Check Laravel logs: storage/logs/laravel.log\n\n";

echo "═══════════════════════════════════════════════════════\n";
echo "Diagnostic Complete! 🎉\n";
echo "═══════════════════════════════════════════════════════\n\n";
