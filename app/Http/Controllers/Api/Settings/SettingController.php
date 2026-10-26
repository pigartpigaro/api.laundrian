<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Outlet;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    private const DEFAULTS = [
        'store_name' => 'Londrian App',
        'phone' => '',
        'address' => '',
        'receipt_footer' => 'Terima kasih telah menggunakan layanan kami.',
        'receipt_width' => '58mm',
        'member_promo_active' => false,
        'member_promo_required_transactions' => 10,
        'member_promo_minimum_subtotal' => 75000,
        'member_promo_discount_percent' => 20,
            'website_active' => false,
            'website_headline' => null,
            'website_description' => null,
            'website_theme' => 'saffron-cream',
        'website_hero_layout' => 'horizontal',
        'website_primary_color' => '#d4a600',
        'website_secondary_color' => '#fff0b8',
        'website_accent_color' => null,
        'website_about_title' => null,
        'website_about_text' => null,
        'website_services_title' => null,
        'website_services_intro' => null,
        'website_services' => [],
        'website_gallery_title' => null,
        'website_contact_title' => null,
        'website_contact_text' => null,
        'website_whatsapp_button_text' => null,
        'website_footer_text' => null,
        'website_footer_tagline' => null,
        'website_footer_support_url' => null,
        'website_footer_support_title' => 'Dukungan',
        'website_footer_show_logo' => true,
        'website_footer_show_support' => true,
        'website_footer_show_contact' => true,
        'website_footer_contact_title' => 'Kontak outlet',
        'website_footer_logo_path' => null,
        'website_footer_logo_mode' => 'logo_tagline',
        'website_footer_logo_size' => 120,
        'website_footer_payment_logos' => [],
        'website_show_services' => true,
        'website_service_ids' => null,
        'website_show_about' => true,
        'website_show_gallery' => false,
        'website_show_location' => true,
        'website_show_prices' => true,
        'website_map_url' => null,
        'website_whatsapp_message' => null,
        'website_instagram_url' => null,
        'website_facebook_url' => null,
        'website_hours_active' => false,
        'website_hours' => [],
        'website_seo_title' => null,
        'website_seo_description' => null,
        'website_search_index' => true,
        'website_hero_image_path' => null,
        'website_gallery' => [],
    ];

    public function show(Request $request): JsonResponse
    {
        $outletId = $request->user()->role === 'SUPERADMIN' ? $request->header('X-Outlet-ID') : $request->user()->outlet_id;
        $settings = self::DEFAULTS;
        $outlet = Outlet::query()->find($outletId);
        $settings['store_name'] = $outlet?->nama ?? self::DEFAULTS['store_name'];
        $settings['outlet_logo_path'] = $outlet?->logo_path;
        $settings['outlet_logo_updated_at'] = $outlet?->updated_at?->timestamp;
        $settings['outlet_logo_url'] = $outlet?->logo_path
            ? "/api/public/outlets/{$outlet->id}/logo?v={$outlet->updated_at?->timestamp}"
            : null;
        foreach (Setting::query()->get() as $setting) {
            if ($setting->key === 'store_name') continue;
            $settings[$setting->key] = $setting->value['value'] ?? null;
        }

        $settings['member_promo_active'] = $this->settingBoolean($settings['member_promo_active']);
        $settings['member_promo_required_transactions'] = (int) $settings['member_promo_required_transactions'];
        $settings['member_promo_minimum_subtotal'] = (float) $settings['member_promo_minimum_subtotal'];
        $settings['member_promo_discount_percent'] = (float) $settings['member_promo_discount_percent'];
        foreach (['website_active', 'website_show_services', 'website_show_about', 'website_show_gallery', 'website_show_location', 'website_show_prices', 'website_hours_active', 'website_search_index', 'website_footer_show_logo', 'website_footer_show_support', 'website_footer_show_contact'] as $key) {
            $settings[$key] = $this->settingBoolean($settings[$key]);
        }
        $settings['website_hours'] = $this->normalizeWebsiteHours($settings['website_hours']);
        $settings['website_gallery_urls'] = collect($settings['website_gallery'] ?? [])
            ->map(fn (string $path) => $this->websiteAssetUrl($outletId, $path))->values();
        $settings['website_footer_payment_logo_urls'] = collect($settings['website_footer_payment_logos'] ?? [])
            ->map(function (array|string $logo) use ($outletId) {
                $path = is_array($logo) ? ($logo['path'] ?? '') : $logo;
                return $path ? [
                    'name' => is_array($logo) ? ($logo['name'] ?? pathinfo($path, PATHINFO_FILENAME)) : pathinfo($path, PATHINFO_FILENAME),
                    'url' => $this->websiteAssetUrl($outletId, $path),
                ] : null;
            })->filter()->values();
        $settings['website_footer_logo_url'] = $settings['website_footer_logo_path']
            ? $this->websiteAssetUrl($outletId, $settings['website_footer_logo_path'])
            : null;
        $settings['website_footer_logo_mode'] = in_array($settings['website_footer_logo_mode'], ['logo_tagline', 'logo', 'tagline'], true)
            ? $settings['website_footer_logo_mode']
            : 'logo_tagline';
        $settings['website_footer_logo_size'] = (int) $settings['website_footer_logo_size'];
        $settings['website_hero_image_url'] = $settings['website_hero_image_path']
            ? $this->websiteAssetUrl($outletId, $settings['website_hero_image_path'])
            : null;
        unset($settings['website_gallery'], $settings['website_hero_image_path'], $settings['website_footer_payment_logos'], $settings['website_footer_logo_path']);

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['sometimes', 'string', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:500'],
            'receipt_width' => ['sometimes', 'in:58mm,80mm'],
            'member_promo_active' => ['sometimes', 'boolean'],
            'member_promo_required_transactions' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'member_promo_minimum_subtotal' => ['sometimes', 'numeric', 'min:1', 'max:9999999999'],
            'member_promo_discount_percent' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'website_active' => ['sometimes', 'boolean'],
            'website_headline' => ['sometimes', 'nullable', 'string', 'max:48'],
            'website_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'website_theme' => ['sometimes', 'in:saffron-cream'],
            'website_hero_layout' => ['sometimes', 'in:horizontal,vertical'],
            'website_primary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'website_secondary_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'website_accent_color' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'website_about_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website_about_text' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'website_services_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website_services_intro' => ['sometimes', 'nullable', 'string', 'max:240'],
            'website_services' => ['sometimes'],
            'website_gallery_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website_contact_title' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website_contact_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'website_whatsapp_button_text' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website_footer_text' => ['sometimes', 'nullable', 'string', 'max:160'],
            'website_footer_tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'website_footer_support_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'website_footer_support_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'website_footer_show_logo' => ['sometimes', 'boolean'],
            'website_footer_show_support' => ['sometimes', 'boolean'],
            'website_footer_show_contact' => ['sometimes', 'boolean'],
            'website_footer_contact_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'website_footer_logo_mode' => ['sometimes', 'in:logo_tagline,logo,tagline'],
            'website_footer_logo_size' => ['sometimes', 'integer', 'min:32', 'max:240'],
            'website_footer_payment_logos' => ['sometimes', 'array', 'max:6'],
            'website_footer_payment_logos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'website_footer_payment_logo_remove' => ['sometimes', 'array'],
            'website_footer_payment_logo_remove.*' => ['integer', 'min:0', 'max:5'],
            'website_show_services' => ['sometimes', 'boolean'],
            'website_service_ids' => ['sometimes'],
            'website_show_about' => ['sometimes', 'boolean'],
            'website_show_gallery' => ['sometimes', 'boolean'],
            'website_show_location' => ['sometimes', 'boolean'],
            'website_show_prices' => ['sometimes', 'boolean'],
            'website_map_url' => ['sometimes', 'nullable', 'url', 'max:1000'],
            'website_whatsapp_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'website_instagram_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'website_facebook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'website_hours_active' => ['sometimes', 'boolean'],
            'website_hours' => ['sometimes', 'array'],
            'website_hours.*.closed' => ['required', 'boolean'],
            'website_hours.*.open' => ['nullable', 'date_format:H:i'],
            'website_hours.*.close' => ['nullable', 'date_format:H:i'],
            'website_seo_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'website_seo_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'website_search_index' => ['sometimes', 'boolean'],
            'website_hero_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'website_clear_hero_image' => ['sometimes', 'boolean'],
            'website_footer_logo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'website_clear_footer_logo' => ['sometimes', 'boolean'],
            'website_gallery_images' => ['sometimes', 'array', 'max:4'],
            'website_gallery_images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'website_gallery_remove' => ['sometimes', 'array'],
            'website_gallery_remove.*' => ['integer', 'min:0', 'max:3'],
            'logo' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $hoursActive = filter_var($data['website_hours_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($hoursActive) {
            $hoursByDay = $data['website_hours'] ?? [];
            foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
                if (! array_key_exists($day, $hoursByDay)) {
                    throw ValidationException::withMessages([
                        'website_hours' => 'Lengkapi jam operasional untuk semua hari.',
                    ]);
                }
            }
            foreach ($hoursByDay as $day => $hours) {
                if (! ($hours['closed'] ?? false) && (empty($hours['open']) || empty($hours['close']))) {
                    throw ValidationException::withMessages([
                        "website_hours.{$day}.open" => 'Isi jam buka dan jam tutup, atau tandai hari tersebut sebagai tutup.',
                    ]);
                }
            }
        }

        $outletId = $request->user()->role === 'SUPERADMIN' ? $request->header('X-Outlet-ID') : $request->user()->outlet_id;
        if (array_key_exists('website_services', $data)) {
            $services = $data['website_services'];
            if (is_string($services)) $services = json_decode($services, true);
            $validator = Validator::make(['services' => $services], [
                'services' => ['present', 'array', 'max:30'],
                'services.*.name' => ['required', 'string', 'max:80'],
                'services.*.description' => ['nullable', 'string', 'max:400'],
                'services.*.icon' => ['required', 'in:laundry-kiloan,laundry-satuan,bedding,household,shoes,shirt,person,curtains,carpet,bag,iron'],
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages(['website_services' => 'Periksa nama, deskripsi, dan ikon layanan website. Maksimal 30 layanan.']);
            }
            $data['website_services'] = array_values(array_map(fn (array $service) => [
                'name' => trim($service['name']),
                'description' => trim($service['description'] ?? ''),
                'icon' => $service['icon'],
            ], $services));
        }
        if (array_key_exists('website_service_ids', $data)) {
            $serviceIds = $data['website_service_ids'];
            if (is_string($serviceIds)) $serviceIds = json_decode($serviceIds, true);
            if (! is_array($serviceIds)) {
                throw ValidationException::withMessages(['website_service_ids' => 'Pilih daftar layanan yang valid.']);
            }
            foreach ($serviceIds as $serviceId) {
                if (filter_var($serviceId, FILTER_VALIDATE_INT) === false) {
                    throw ValidationException::withMessages(['website_service_ids' => 'ID layanan tidak valid.']);
                }
            }
            $serviceIds = array_values(array_unique(array_map('intval', $serviceIds)));
            $ownedServiceCount = count($serviceIds)
                ? Service::query()->where('outlet_id', $outletId)->whereIn('id', $serviceIds)->count()
                : 0;
            if ($ownedServiceCount !== count($serviceIds)) {
                throw ValidationException::withMessages(['website_service_ids' => 'Daftar layanan harus berasal dari outlet ini.']);
            }
            $data['website_service_ids'] = $serviceIds;
        }
        if (array_key_exists('website_hours', $data)) {
            $data['website_hours'] = $this->normalizeWebsiteHours($data['website_hours']);
        }
        $logo = $data['logo'] ?? null;
        $heroImage = $data['website_hero_image'] ?? null;
        $footerLogo = $data['website_footer_logo'] ?? null;
        $galleryImages = $data['website_gallery_images'] ?? [];
        $galleryRemovals = $data['website_gallery_remove'] ?? [];
        $paymentLogoImages = $data['website_footer_payment_logos'] ?? [];
        $paymentLogoRemovals = $data['website_footer_payment_logo_remove'] ?? [];
        $clearHeroImage = (bool) ($data['website_clear_hero_image'] ?? false);
        $clearFooterLogo = (bool) ($data['website_clear_footer_logo'] ?? false);
        unset($data['logo']);
        unset($data['website_hero_image'], $data['website_gallery_images'], $data['website_gallery_remove'], $data['website_clear_hero_image'], $data['website_footer_logo'], $data['website_clear_footer_logo'], $data['website_footer_payment_logos'], $data['website_footer_payment_logo_remove']);

        if ($galleryImages || $galleryRemovals) {
            $currentGallery = Setting::query()->where('key', 'website_gallery')->first();
            $currentPaths = array_values($currentGallery?->value['value'] ?? []);
            $remainingPaths = array_filter($currentPaths, fn ($path, $index) => ! in_array($index, array_map('intval', $galleryRemovals), true), ARRAY_FILTER_USE_BOTH);
            if (count($remainingPaths) + count($galleryImages) > 4) {
                return response()->json(['message' => 'Galeri maksimal 4 foto. Hapus foto lama sebelum menambahkan yang baru.'], 422);
            }
        }
        if ($paymentLogoImages || $paymentLogoRemovals) {
            $currentPaymentSetting = Setting::query()->where('key', 'website_footer_payment_logos')->first();
            $currentPaymentLogos = array_values($currentPaymentSetting?->value['value'] ?? []);
            $removedPaymentIndexes = array_unique(array_map('intval', $paymentLogoRemovals));
            $remainingPaymentLogos = array_filter($currentPaymentLogos, fn ($logo, $index) => ! in_array($index, $removedPaymentIndexes, true), ARRAY_FILTER_USE_BOTH);
            if (count($remainingPaymentLogos) + count($paymentLogoImages) > 6) {
                return response()->json(['message' => 'Maksimal 6 logo pembayaran. Hapus logo lama sebelum menambahkan yang baru.'], 422);
            }
        }
        foreach ($data as $key => $value) {
            if ($key === 'store_name') {
                Outlet::query()->whereKey($outletId)->update(['nama' => $value]);
                continue;
            }
            Setting::query()->updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
        }

        $heroSetting = Setting::query()->firstOrNew(['key' => 'website_hero_image_path']);
        $heroPath = $heroSetting->value['value'] ?? null;
        if ($heroImage) {
            $newPath = $heroImage->storePublicly("outlet-websites/{$outletId}", 'public');
            abort_unless($newPath, 500, 'Foto utama website gagal disimpan.');
            if ($heroPath) Storage::disk('public')->delete($heroPath);
            $heroPath = $newPath;
        } elseif ($clearHeroImage && $heroPath) {
            Storage::disk('public')->delete($heroPath);
            $heroPath = null;
        }
        if ($heroImage || $clearHeroImage) {
            $heroSetting->value = ['value' => $heroPath];
            $heroSetting->save();
        }

        if ($footerLogo || $clearFooterLogo) {
            $footerLogoSetting = Setting::query()->firstOrNew(['key' => 'website_footer_logo_path']);
            $previousPath = $footerLogoSetting->value['value'] ?? null;
            if ($footerLogo) {
                $path = $footerLogo->storePublicly("outlet-websites/{$outletId}", 'public');
                abort_unless($path, 500, 'Logo footer gagal disimpan.');
                $footerLogoSetting->value = ['value' => $path];
                if ($previousPath) Storage::disk('public')->delete($previousPath);
            } else {
                $footerLogoSetting->value = ['value' => null];
                if ($previousPath) Storage::disk('public')->delete($previousPath);
            }
            $footerLogoSetting->save();
        }

        if ($galleryImages || $galleryRemovals) {
            $gallerySetting = Setting::query()->firstOrNew(['key' => 'website_gallery']);
            $gallery = array_values($gallerySetting->value['value'] ?? []);
            foreach (array_unique(array_map('intval', $galleryRemovals)) as $index) {
                if (isset($gallery[$index])) Storage::disk('public')->delete($gallery[$index]);
                unset($gallery[$index]);
            }
            $gallery = array_values($gallery);
            foreach ($galleryImages as $image) {
                $path = $image->storePublicly("outlet-websites/{$outletId}", 'public');
                abort_unless($path, 500, 'Foto galeri website gagal disimpan.');
                $gallery[] = $path;
            }
            $gallerySetting->value = ['value' => $gallery];
            $gallerySetting->save();
        }

        if ($paymentLogoImages || $paymentLogoRemovals) {
            $paymentSetting = Setting::query()->firstOrNew(['key' => 'website_footer_payment_logos']);
            $paymentLogos = array_values($paymentSetting->value['value'] ?? []);
            foreach ($removedPaymentIndexes as $index) {
                if (isset($paymentLogos[$index])) {
                    $oldPath = is_array($paymentLogos[$index]) ? ($paymentLogos[$index]['path'] ?? '') : $paymentLogos[$index];
                    if ($oldPath) Storage::disk('public')->delete($oldPath);
                    unset($paymentLogos[$index]);
                }
            }
            $paymentLogos = array_values($paymentLogos);
            foreach ($paymentLogoImages as $image) {
                $path = $image->storePublicly("outlet-websites/{$outletId}", 'public');
                abort_unless($path, 500, 'Logo pembayaran gagal disimpan.');
                $paymentLogos[] = ['path' => $path, 'name' => pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)];
            }
            $paymentSetting->value = ['value' => $paymentLogos];
            $paymentSetting->save();
        }

        if ($logo) {
            $outlet = Outlet::query()->findOrFail($outletId);
            $previousPath = $outlet->logo_path;
            $path = $logo->storePublicly("outlet-logos/{$outlet->id}", 'public');
            abort_unless($path, 500, 'Logo outlet gagal disimpan.');
            $outlet->update(['logo_path' => $path]);
            if ($previousPath && $previousPath !== $path) Storage::disk('public')->delete($previousPath);
        }

        return $this->show($request);
    }

    public function logo(Outlet $outlet)
    {
        abort_unless($outlet->logo_path && Storage::disk('public')->exists($outlet->logo_path), 404);

        return Storage::disk('public')->response($outlet->logo_path, null, ['Cache-Control' => 'public, max-age=300']);
    }

    public function publicWebsite(Outlet $outlet): JsonResponse
    {
        $settings = Setting::query()->withoutGlobalScopes()
            ->where('outlet_id', $outlet->id)
            ->whereIn('key', [
                'phone', 'address', 'website_active', 'website_headline', 'website_description', 'website_theme', 'website_hero_layout',
                'website_accent_color', 'website_primary_color', 'website_secondary_color', 'website_about_title', 'website_about_text', 'website_services_title',
                'website_services_intro', 'website_services', 'website_gallery_title', 'website_contact_title', 'website_contact_text', 'website_show_services',
                'website_whatsapp_button_text', 'website_footer_text', 'website_footer_tagline', 'website_footer_support_url', 'website_footer_support_title',
                'website_footer_show_logo', 'website_footer_show_support', 'website_footer_show_contact', 'website_footer_contact_title', 'website_footer_payment_logos',
                'website_footer_logo_path', 'website_footer_logo_mode', 'website_footer_logo_size',
                'website_show_about', 'website_show_gallery', 'website_show_location', 'website_show_prices',
                'website_service_ids',
                'website_map_url', 'website_whatsapp_message', 'website_instagram_url', 'website_facebook_url',
                'website_hours_active', 'website_hours', 'website_seo_title', 'website_seo_description',
                'website_search_index', 'website_hero_image_path', 'website_gallery',
            ])
            ->get()->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value['value'] ?? null]);

        abort_unless($outlet->hasActiveAccess() && $this->settingBoolean($settings->get('website_active', false)), 404, 'Website outlet ini belum dipublikasikan.');

        $heroPath = $settings->get('website_hero_image_path');
        $galleryPaths = $settings->get('website_gallery', []);

        return response()->json(['data' => [
            'id' => $outlet->id,
            'name' => $outlet->nama,
            'logo_url' => $outlet->logo_path
                ? url("/api/public/outlets/{$outlet->id}/logo?v={$outlet->updated_at?->timestamp}")
                : null,
            'phone' => $settings->get('phone', ''),
            'address' => $settings->get('address', ''),
            'headline' => $settings->get('website_headline'),
            'description' => $settings->get('website_description'),
            'theme' => 'saffron-cream',
            'hero_layout' => in_array($settings->get('website_hero_layout'), ['horizontal', 'vertical'], true)
                ? $settings->get('website_hero_layout')
                : 'horizontal',
            'primary_color' => $settings->get('website_primary_color', $settings->get('website_accent_color', '#d4a600')),
            'secondary_color' => $settings->get('website_secondary_color', '#fff0b8'),
            'accent_color' => $settings->get('website_primary_color', $settings->get('website_accent_color', '#d4a600')),
            'about_title' => $settings->get('website_about_title'),
            'about_text' => $settings->get('website_about_text'),
            'services_title' => $settings->get('website_services_title'),
            'services_intro' => $settings->get('website_services_intro'),
            'services' => $settings->get('website_services', []),
            'gallery_title' => $settings->get('website_gallery_title'),
            'contact_title' => $settings->get('website_contact_title'),
            'contact_text' => $settings->get('website_contact_text'),
            'whatsapp_button_text' => $settings->get('website_whatsapp_button_text'),
            'footer_text' => $settings->get('website_footer_text'),
            'footer_tagline' => $settings->get('website_footer_tagline'),
            'footer_logo_url' => $settings->get('website_footer_logo_path') ? $this->websiteAssetUrl($outlet->id, $settings->get('website_footer_logo_path')) : null,
            'footer_logo_mode' => in_array($settings->get('website_footer_logo_mode', 'logo_tagline'), ['logo_tagline', 'logo', 'tagline'], true) ? $settings->get('website_footer_logo_mode', 'logo_tagline') : 'logo_tagline',
            'footer_logo_size' => (int) $settings->get('website_footer_logo_size', 120),
            'footer_support_url' => $settings->get('website_footer_support_url'),
            'footer_support_title' => $settings->get('website_footer_support_title', 'Dukungan'),
            'footer_show_logo' => $this->settingBoolean($settings->get('website_footer_show_logo', true), true),
            'footer_show_support' => $this->settingBoolean($settings->get('website_footer_show_support', true), true),
            'footer_show_contact' => $this->settingBoolean($settings->get('website_footer_show_contact', true), true),
            'footer_contact_title' => $settings->get('website_footer_contact_title', 'Kontak outlet'),
            'footer_payment_logos' => collect($settings->get('website_footer_payment_logos', []))
                ->map(function (array|string $logo) use ($outlet) {
                    $path = is_array($logo) ? ($logo['path'] ?? '') : $logo;
                    return $path ? [
                        'name' => is_array($logo) ? ($logo['name'] ?? pathinfo($path, PATHINFO_FILENAME)) : pathinfo($path, PATHINFO_FILENAME),
                        'url' => $this->websiteAssetUrl($outlet->id, $path),
                    ] : null;
                })->filter()->values(),
            'show_services' => $this->settingBoolean($settings->get('website_show_services', true), true),
            'show_about' => $this->settingBoolean($settings->get('website_show_about', true), true),
            'show_gallery' => $this->settingBoolean($settings->get('website_show_gallery', false)),
            'show_location' => $this->settingBoolean($settings->get('website_show_location', true), true),
            'show_prices' => $this->settingBoolean($settings->get('website_show_prices', true), true),
            'map_url' => $settings->get('website_map_url'),
            'whatsapp_message' => $settings->get('website_whatsapp_message'),
            'instagram_url' => $settings->get('website_instagram_url'),
            'facebook_url' => $settings->get('website_facebook_url'),
            'hours_active' => $this->settingBoolean($settings->get('website_hours_active', false)),
            'hours' => $this->normalizeWebsiteHours($settings->get('website_hours', [])),
            'seo_title' => $settings->get('website_seo_title'),
            'seo_description' => $settings->get('website_seo_description'),
            'search_index' => $this->settingBoolean($settings->get('website_search_index', true), true),
            'hero_image_url' => $heroPath ? $this->websiteAssetUrl($outlet->id, $heroPath) : null,
            'gallery_image_urls' => collect($galleryPaths)->map(fn (string $path) => $this->websiteAssetUrl($outlet->id, $path))->values(),
        ]]);
    }

    public function websiteMedia(Outlet $outlet, string $filename)
    {
        abort_unless(basename($filename) === $filename, 404);
        $published = Setting::query()->withoutGlobalScopes()
            ->where('outlet_id', $outlet->id)->where('key', 'website_active')->first();
        abort_unless($outlet->hasActiveAccess() && (bool) ($published?->value['value'] ?? false), 404);
        $path = "outlet-websites/{$outlet->id}/{$filename}";
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    private function websiteAssetUrl(int|string $outletId, string $path): string
    {
        return url("/api/public/outlets/{$outletId}/website-media/" . basename($path));
    }

    private function normalizeWebsiteHours(mixed $hours): array
    {
        if (! is_array($hours)) return [];

        foreach ($hours as $day => $schedule) {
            if (! is_array($schedule)) continue;
            $hours[$day] = [
                'closed' => filter_var($schedule['closed'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'open' => $schedule['open'] ?? '',
                'close' => $schedule['close'] ?? '',
            ];
        }

        return $hours;
    }

    private function settingBoolean(mixed $value, bool $default = false): bool
    {
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
