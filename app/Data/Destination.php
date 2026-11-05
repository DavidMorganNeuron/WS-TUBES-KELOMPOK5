<?php

namespace App\Data;

use Illuminate\Support\Str;

final readonly class Destination
{
    /**
     * @param  list<string>  $types
     * @param  list<string>  $keywords
     * @param  list<string>  $prices
     * @param  list<string>  $facilities
     * @param  list<string>  $activities
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $alternativeName,
        public string $shortDescription,
        public string $fullDescription,
        public array $types,
        public array $keywords,
        public string $location,
        public string $address,
        public ?float $latitude,
        public ?float $longitude,
        public string $directions,
        public string $openingHours,
        public array $prices,
        public string $phone,
        public string $email,
        public string $instagram,
        public array $facilities,
        public array $activities,
        public string $media,
        public ?float $rating,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: self::text($data['idDestinasi'] ?? ''),
            name: self::text($data['namaDestinasi'] ?? ''),
            alternativeName: self::text($data['namaAlternatif'] ?? ''),
            shortDescription: self::text($data['deskripsiSingkat'] ?? ''),
            fullDescription: self::text($data['deskripsiLengkap'] ?? ''),
            types: self::splitValues($data['jenisDestinasi'] ?? ''),
            keywords: self::splitValues($data['kataKunci'] ?? ''),
            location: self::text($data['beradaDi'] ?? ''),
            address: self::text($data['alamat'] ?? ''),
            latitude: self::coordinate($data['lintang'] ?? null, -90, 90),
            longitude: self::coordinate($data['bujur'] ?? null, -180, 180),
            directions: self::text($data['petunjukLokasi'] ?? ''),
            openingHours: self::text($data['jamOperasional'] ?? ''),
            prices: self::splitValues($data['hargaMasuk'] ?? ''),
            phone: self::text($data['noHpResmi'] ?? ''),
            email: self::text($data['emailResmi'] ?? ''),
            instagram: self::text($data['instagramResmi'] ?? ''),
            facilities: self::splitValues($data['memilikiFasilitas'] ?? ''),
            activities: self::splitValues($data['dapatMelakukan'] ?? ''),
            media: self::text($data['memilikiMedia'] ?? ''),
            rating: self::rating($data['ratingPengunjung'] ?? null),
        );
    }

    public function province(): string
    {
        $haystack = Str::lower($this->location.' '.$this->address);

        return Str::contains($haystack, ['aceh', 'banda aceh', 'sabang'])
            ? 'Aceh'
            : 'Sumatera Utara';
    }

    public function isAceh(): bool
    {
        return $this->province() === 'Aceh';
    }

    public function primaryType(): string
    {
        return $this->types[0] ?? 'Destinasi';
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function imageUrl(): string
    {
        if ($this->media !== '') {
            return $this->media;
        }

        $fallback = [
            'Alam' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=1200&q=80',
            'Budaya' => 'https://images.unsplash.com/photo-1555400038-63f5ba517a47?auto=format&fit=crop&w=1200&q=80',
            'Religi' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769?auto=format&fit=crop&w=1200&q=80',
            'Bahari' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
            'Kuliner' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80',
            'Buatan' => 'https://images.unsplash.com/photo-1552465011-b4e21bf6e79a?auto=format&fit=crop&w=1200&q=80',
        ];

        return $fallback[$this->primaryType()] ?? $fallback['Alam'];
    }

    /**
     * Nilai harga terendah untuk pengurutan.
     * "Gratis" dianggap 0, sedangkan data harga kosong menghasilkan null.
     */
    public function lowestPrice(): ?float
    {
        $numeric = array_filter($this->prices, is_numeric(...));

        if ($numeric !== []) {
            return min(array_map(floatval(...), $numeric));
        }

        return in_array('Gratis', $this->prices, true) ? 0.0 : null;
    }

    /**
     * Harga terendah sebagai label ringkas ("mulai dari").
     * Teks non-numerik seperti "Gratis" dipertahankan apa adanya.
     */
    public function priceLabel(): string
    {
        $numeric = array_filter($this->prices, is_numeric(...));

        if ($numeric !== []) {
            return self::formatPrice((string) min(array_map(floatval(...), $numeric)));
        }

        return $this->prices[0] ?? '—';
    }

    /**
     * Seluruh harga yang sudah diformat dan tanpa duplikat (untuk halaman detail).
     *
     * @return list<string>
     */
    public function priceLabels(): array
    {
        return array_values(array_unique(array_map(self::formatPrice(...), $this->prices)));
    }

    private static function formatPrice(string $price): string
    {
        return is_numeric($price)
            ? 'Rp '.number_format((float) $price, 0, ',', '.')
            : $price;
    }

    public function ratingLabel(): string
    {
        return $this->rating === null ? '—' : number_format($this->rating, 1);
    }

    public function mapsUrl(): string
    {
        if ($this->hasCoordinates()) {
            return 'https://www.google.com/maps/search/?api=1&query='.$this->latitude.','.$this->longitude;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(trim($this->name.' '.$this->address));
    }

    /**
     * @return array<string, mixed>
     */
    public function toMapMarker(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->primaryType(),
            'types' => $this->types,
            'rating' => $this->rating,
            'rating_label' => $this->ratingLabel(),
            'province' => $this->province(),
            'location' => $this->location,
            'price' => $this->priceLabel(),
            'hours' => $this->openingHours,
            'image' => $this->imageUrl(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'url' => route('destination.detail', $this->id),
        ];
    }

    private static function text(mixed $value): string
    {
        $text = trim((string) $value);

        return in_array($text, ['', '-', '(kosong)'], true) ? '' : $text;
    }

    /**
     * @return list<string>
     */
    private static function splitValues(mixed $value): array
    {
        $text = self::text($value);

        if ($text === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), explode('|', $text)),
            static fn (string $item): bool => $item !== '' && $item !== '-',
        ));
    }

    private static function coordinate(mixed $value, float $minimum, float $maximum): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $coordinate = (float) $value;

        return $coordinate >= $minimum && $coordinate <= $maximum ? $coordinate : null;
    }

    private static function rating(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
