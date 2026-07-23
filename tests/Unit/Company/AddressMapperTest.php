<?php

declare(strict_types=1);

namespace AresApi\Tests\Unit\Company;

use AresApi\Company\DTO\Address;
use AresApi\Company\Mapper\AddressMapper;
use AresApi\Exception\InvalidResponseException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
#[CoversClass(AddressMapper::class)]
final class AddressMapperTest extends TestCase
{
    public function testItMapsAnAresAddress(): void
    {
        $address = new AddressMapper()->map([
            'kodStatu' => 'CZ',
            'nazevStatu' => 'Česká republika',
            'kodKraje' => 19,
            'nazevKraje' => 'Hlavní město Praha',
            'kodOkresu' => 3100,
            'nazevOkresu' => 'Praha',
            'kodObce' => 554782,
            'nazevObce' => 'Praha',
            'kodSpravnihoObvodu' => 43,
            'nazevSpravnihoObvodu' => 'Praha 4',
            'kodMestskehoObvodu' => 43,
            'nazevMestskehoObvodu' => 'Praha 4',
            'kodMestskeCastiObvodu' => 500119,
            'nazevMestskeCastiObvodu' => 'Praha 4',
            'kodUlice' => 444375,
            'nazevUlice' => 'Budějovická',
            'cisloDomovni' => 778,
            'typCisloDomovni' => 1,
            'doplnekAdresy' => 'budova A',
            'kodCastiObce' => 490130,
            'nazevCastiObce' => 'Michle',
            'cisloOrientacni' => 3,
            'cisloOrientacniPismeno' => 'a',
            'kodAdresnihoMista' => 41405609,
            'psc' => 14000,
            'pscTxt' => '140 00',
            'textovaAdresa' => 'Budějovická 778/3a, 140 00 Praha 4',
            'cisloDoAdresy' => '778/3a',
            'standardizaceAdresy' => true,
        ]);

        self::assertSame('CZ', $address->countryCode());
        self::assertSame('Česká republika', $address->countryName());
        self::assertSame(19, $address->regionCode());
        self::assertSame('Hlavní město Praha', $address->regionName());
        self::assertSame(3100, $address->districtCode());
        self::assertSame('Praha', $address->districtName());
        self::assertSame(554782, $address->municipalityCode());
        self::assertSame('Praha', $address->municipalityName());
        self::assertSame(43, $address->administrativeDistrictCode());
        self::assertSame('Praha 4', $address->administrativeDistrictName());
        self::assertSame(43, $address->cityDistrictCode());
        self::assertSame('Praha 4', $address->cityDistrictName());
        self::assertSame(500119, $address->municipalityDistrictCode());
        self::assertSame('Praha 4', $address->municipalityDistrictName());
        self::assertSame(444375, $address->streetCode());
        self::assertSame('Budějovická', $address->streetName());
        self::assertSame(778, $address->houseNumber());
        self::assertSame(1, $address->houseNumberTypeCode());
        self::assertSame('budova A', $address->addressComplement());
        self::assertSame(490130, $address->municipalityPartCode());
        self::assertSame('Michle', $address->municipalityPartName());
        self::assertSame(3, $address->orientationNumber());
        self::assertSame('a', $address->orientationNumberLetter());
        self::assertSame(41405609, $address->addressPlaceCode());
        self::assertSame(14000, $address->postalCode());
        self::assertSame('140 00', $address->postalCodeText());
        self::assertSame(
            'Budějovická 778/3a, 140 00 Praha 4',
            $address->formattedAddress(),
        );
        self::assertSame('778/3a', $address->addressNumber());
        self::assertTrue($address->standardized());
    }

    public function testItLeavesAbsentAddressFieldsNull(): void
    {
        $address = new AddressMapper()->map([]);

        self::assertNull($address->countryCode());
        self::assertNull($address->streetName());
        self::assertNull($address->postalCode());
        self::assertNull($address->standardized());
    }

    public function testItRejectsAFieldWithAnUnexpectedType(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('"psc" must be integer or null');

        new AddressMapper()->map(['psc' => '14000']);
    }

    public function testItRejectsANonEmptyListInsteadOfAnAddressObject(): void
    {
        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionMessageIsOrContains('must be an object');

        new AddressMapper()->map(['unexpected']);
    }
}
