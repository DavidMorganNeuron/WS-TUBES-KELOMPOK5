<?php

namespace Tests\Unit;

use App\Data\Destination;
use App\Services\Seo\SeoDataFactory;
use Illuminate\Http\Request;
use Tests\TestCase;

class SeoDataFactoryTest extends TestCase
{
    public function test_invalid_or_credentialed_image_urls_use_the_placeholder_asset(): void
    {
        $factory = app(SeoDataFactory::class);
        foreach ([
            'javascript:alert(1)',
            'https://user:password@example.test/image.jpg',
            'https://example.test/image file.jpg',
        ] as $imageUrl) {
            $destination = Destination::fromArray([
                'idDestinasi' => 'ws-001',
                'namaDestinasi' => 'Contoh',
                'memilikiMedia' => $imageUrl,
            ]);
            $seo = $factory->destination($destination);

            $this->assertSame(asset('images/wayfinder-og.png'), $seo->imageUrl);
            $this->assertTrue($seo->isPlaceholderImage);
        }

        $image = getimagesize(public_path('images/wayfinder-og.png'));

        $this->assertNotFalse($image);
        $this->assertSame(1200, $image[0]);
        $this->assertSame(630, $image[1]);
    }

    public function test_valid_external_image_urls_are_preserved_unchanged(): void
    {
        $url = 'https://i.pinimg.com/originals/example.jpg?width=1200';
        $destination = Destination::fromArray([
            'idDestinasi' => 'ws-002',
            'namaDestinasi' => 'Contoh',
            'memilikiMedia' => $url,
        ]);

        $seo = app(SeoDataFactory::class)->destination($destination);

        $this->assertSame($url, $seo->imageUrl);
        $this->assertFalse($seo->isPlaceholderImage);
    }

    public function test_filtered_destination_pages_are_canonicalized_and_marked_noindex(): void
    {
        $seo = app(SeoDataFactory::class)->destinations(
            Request::create('/destinations?q=Danau&page=2'),
            ['q' => 'Danau', 'sort' => 'rating'],
        );

        $this->assertSame(route('destinations').'?q=Danau&sort=rating&page=2', $seo->canonicalUrl);
        $this->assertTrue($seo->noindex);
    }
}
