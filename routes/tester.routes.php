<?php
Flight::route('GET /tester', [Tester::class, 'get']);
Flight::route('POST /tester', [Tester::class, 'post']);