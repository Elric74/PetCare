<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache cleared!\n";
} else {
    echo "OPcache not enabled\n";
}

if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "APC cache cleared!\n";
}

echo "Cache clear attempted.\n";
