<?php

namespace Verso\Plugins\Shop\Exceptions;

use RuntimeException;

// Thrown when a payment gateway cannot start or check a payment.
class PaymentFailed extends RuntimeException {}
