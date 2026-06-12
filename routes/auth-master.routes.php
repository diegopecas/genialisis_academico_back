<?php
Flight::route('POST /auth/pre-login', [AuthMaster::class, 'preLogin']);