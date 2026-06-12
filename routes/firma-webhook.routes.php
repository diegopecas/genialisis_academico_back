<?php
// WEBHOOK FIRMA DIGITAL (no requiere X-Tenant)
Flight::route('POST /webhooks/firma', [FirmaWebhook::class, 'procesar']);