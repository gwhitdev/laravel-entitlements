<?php

use Entitlements\Resolvers\StripePlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;

// Minimal user stub that simulates Cashier's Billable trait methods.
function makeStripeBillableUser(string $name, ?string $price, bool $active): Authenticatable
{
    return new class($name, $price, $active) implements Authenticatable {
        public function __construct(
            private string $subscriptionName,
            private ?string $price,
            private bool $active,
        ) {}

        public function subscription(string $name = 'default'): ?object
        {
            if ($name !== $this->subscriptionName) {
                return null;
            }

            $price = $this->price;

            return new class($price) {
                public function __construct(public ?string $stripe_price) {}
            };
        }

        public function subscribed(string $name = 'default'): bool
        {
            return $name === $this->subscriptionName && $this->active;
        }

        public function getAuthIdentifier(): mixed { return 1; }
        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthPassword(): string { return ''; }
        public function getRememberToken(): ?string { return null; }
        public function setRememberToken($value): void {}
        public function getRememberTokenName(): string { return ''; }
        public function getAuthPasswordName(): string { return 'password'; }
    };
}

it('resolves planIdentifier from the default subscription when subscription_name is null', function () {
    config()->set('entitlements.subscription_name', null);

    $user = makeStripeBillableUser('default', 'price_pro', true);
    $resolver = new StripePlanResolver();

    expect($resolver->planIdentifier($user))->toBe('price_pro');
});

it('resolves isActive from the default subscription when subscription_name is null', function () {
    config()->set('entitlements.subscription_name', null);

    $user = makeStripeBillableUser('default', 'price_pro', true);
    $resolver = new StripePlanResolver();

    expect($resolver->isActive($user))->toBeTrue();
});

it('resolves planIdentifier from a named subscription when subscription_name is set', function () {
    config()->set('entitlements.subscription_name', 'main');

    $user = makeStripeBillableUser('main', 'price_enterprise', true);
    $resolver = new StripePlanResolver();

    expect($resolver->planIdentifier($user))->toBe('price_enterprise');
});

it('returns null planIdentifier when the configured subscription name does not match', function () {
    config()->set('entitlements.subscription_name', 'main');

    $user = makeStripeBillableUser('default', 'price_pro', true);
    $resolver = new StripePlanResolver();

    expect($resolver->planIdentifier($user))->toBeNull();
});

it('returns false for isActive when the configured subscription name does not match', function () {
    config()->set('entitlements.subscription_name', 'main');

    $user = makeStripeBillableUser('default', 'price_pro', true);
    $resolver = new StripePlanResolver();

    expect($resolver->isActive($user))->toBeFalse();
});

it('returns null planIdentifier for a user without Cashier methods', function () {
    $user = new class implements Authenticatable {
        public function getAuthIdentifier(): mixed { return 1; }
        public function getAuthIdentifierName(): string { return 'id'; }
        public function getAuthPassword(): string { return ''; }
        public function getRememberToken(): ?string { return null; }
        public function setRememberToken($value): void {}
        public function getRememberTokenName(): string { return ''; }
        public function getAuthPasswordName(): string { return 'password'; }
    };

    $resolver = new StripePlanResolver();

    expect($resolver->planIdentifier($user))->toBeNull();
    expect($resolver->isActive($user))->toBeFalse();
});
