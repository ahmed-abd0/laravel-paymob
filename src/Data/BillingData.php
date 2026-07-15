<?php

namespace Paymob\Laravel\Data;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;

final readonly class BillingData implements Arrayable
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phoneNumber,
        public string $apartment = 'NA',
        public string $floor = 'NA',
        public string $street = 'NA',
        public string $building = 'NA',
        public string $city = 'NA',
        public string $country = 'EG',
        public string $state = 'NA',
        public string $postalCode = 'NA'
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: (string) ($data['first_name'] ?? $data['firstName'] ?? ''),
            lastName: (string) ($data['last_name'] ?? $data['lastName'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            phoneNumber: (string) ($data['phone_number'] ?? $data['phoneNumber'] ?? ''),
            apartment: (string) ($data['apartment'] ?? 'NA'),
            floor: (string) ($data['floor'] ?? 'NA'),
            street: (string) ($data['street'] ?? 'NA'),
            building: (string) ($data['building'] ?? 'NA'),
            city: (string) ($data['city'] ?? 'NA'),
            country: (string) ($data['country'] ?? 'EG'),
            state: (string) ($data['state'] ?? 'NA'),
            postalCode: (string) ($data['postal_code'] ?? $data['postalCode'] ?? 'NA')
        );
    }

    public function toArray(): array
    {
        if ($this->firstName === '' || $this->lastName === '' || $this->phoneNumber === '') throw new InvalidArgumentException('First name, last name, and phone number are required.');
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('A valid billing email is required.');
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone_number' => $this->phoneNumber,
            'apartment' => $this->apartment,
            'floor' => $this->floor,
            'street' => $this->street,
            'building' => $this->building,
            'city' => $this->city,
            'country' => $this->country,
            'state' => $this->state,
            'postal_code' => $this->postalCode
        ];
    }
}
