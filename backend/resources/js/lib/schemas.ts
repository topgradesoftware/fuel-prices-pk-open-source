/**
 * Strict client-side schemas mirroring backend Form Requests.
 * Reject invalid input before network calls — server remains source of truth.
 */

export type SchemaIssue = { field: string; message: string };
export type SchemaResult<T> =
    | { ok: true; data: T }
    | { ok: false; errors: SchemaIssue[] };

const SLUG_RE = /^[a-z0-9]+(?:[_-][a-z0-9]+)*$/;
const COUNTRY_RE = /^[A-Za-z]{2}$/;
const UUID_RE =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function fail(field: string, message: string): SchemaResult<never> {
    return { ok: false, errors: [{ field, message }] };
}

function optionalString(value: unknown): string | null {
    if (value == null || value === '') return null;
    return typeof value === 'string' ? value : String(value);
}

export function parseSlug(value: unknown, field = 'slug', max = 64): SchemaResult<string | null> {
    const s = optionalString(value);
    if (s == null) return { ok: true, data: null };
    if (s.length > max || !SLUG_RE.test(s)) return fail(field, 'Invalid slug.');
    return { ok: true, data: s };
}

export function parseCountry(value: unknown): SchemaResult<string | null> {
    const s = optionalString(value);
    if (s == null) return { ok: true, data: null };
    if (!COUNTRY_RE.test(s)) return fail('country', 'Invalid country code.');
    return { ok: true, data: s.toUpperCase() };
}

export function parseSearch(value: unknown, field = 'q', max = 120): SchemaResult<string | null> {
    const s = optionalString(value);
    if (s == null) return { ok: true, data: null };
    if (s.length > max) return fail(field, `Max length is ${max}.`);
    return { ok: true, data: s };
}

export function parseUuid(value: unknown, field = 'uuid'): SchemaResult<string | null> {
    const s = optionalString(value);
    if (s == null) return { ok: true, data: null };
    if (!UUID_RE.test(s)) return fail(field, 'Invalid UUID.');
    return { ok: true, data: s };
}

export function parsePricesQuery(input: Record<string, unknown>): SchemaResult<{
    city: string | null;
    company: string | null;
    product: string | null;
    country: string | null;
}> {
    const city = parseSlug(input.city, 'city');
    if (!city.ok) return city;
    const company = parseSlug(input.company, 'company');
    if (!company.ok) return company;
    const product = parseSlug(input.product, 'product');
    if (!product.ok) return product;
    const country = parseCountry(input.country);
    if (!country.ok) return country;

    const allowed = new Set(['city', 'company', 'product', 'country']);
    for (const key of Object.keys(input)) {
        if (!allowed.has(key)) return fail(key, 'Unexpected field.');
    }

    return {
        ok: true,
        data: {
            city: city.data,
            company: company.data,
            product: product.data,
            country: country.data,
        },
    };
}

export function parseHistoryQuery(input: Record<string, unknown>): SchemaResult<{
    product: string | null;
    company: string | null;
    country: string | null;
    days: number;
}> {
    const product = parseSlug(input.product, 'product');
    if (!product.ok) return product;
    const company = parseSlug(input.company, 'company');
    if (!company.ok) return company;
    const country = parseCountry(input.country);
    if (!country.ok) return country;

    let days = 30;
    if (input.days != null && input.days !== '') {
        const n = Number(input.days);
        if (!Number.isInteger(n) || n < 1 || n > 365) return fail('days', 'Days must be 1–365.');
        days = n;
    }

    return {
        ok: true,
        data: { product: product.data, company: company.data, country: country.data, days },
    };
}

export function parseNearbyQuery(input: Record<string, unknown>): SchemaResult<{
    lat: number;
    lng: number;
    radius?: number;
    limit?: number;
    q: string | null;
    company: string | null;
}> {
    const lat = Number(input.lat);
    const lng = Number(input.lng);
    if (!Number.isFinite(lat) || lat < -90 || lat > 90) return fail('lat', 'Invalid latitude.');
    if (!Number.isFinite(lng) || lng < -180 || lng > 180) return fail('lng', 'Invalid longitude.');

    let radius: number | undefined;
    if (input.radius != null && input.radius !== '') {
        const n = Number(input.radius);
        if (!Number.isInteger(n) || n < 500 || n > 20000) return fail('radius', 'Radius must be 500–20000.');
        radius = n;
    }

    let limit: number | undefined;
    if (input.limit != null && input.limit !== '') {
        const n = Number(input.limit);
        if (!Number.isInteger(n) || n < 1 || n > 20) return fail('limit', 'Limit must be 1–20.');
        limit = n;
    }

    const q = parseSearch(input.q);
    if (!q.ok) return q;
    const company = parseSlug(input.company, 'company');
    if (!company.ok) return company;

    return {
        ok: true,
        data: { lat, lng, radius, limit, q: q.data, company: company.data },
    };
}

export function parseGoogleAuthBody(input: Record<string, unknown>): SchemaResult<{
    id_token: string;
    device_uuid: string | null;
}> {
    const token = optionalString(input.id_token);
    if (token == null || token.length < 20 || token.length > 8192) {
        return fail('id_token', 'Invalid ID token.');
    }
    const device = parseUuid(input.device_uuid, 'device_uuid');
    if (!device.ok) return device;

    const allowed = new Set(['id_token', 'device_uuid']);
    for (const key of Object.keys(input)) {
        if (!allowed.has(key)) return fail(key, 'Unexpected field.');
    }

    return { ok: true, data: { id_token: token, device_uuid: device.data } };
}

export function parsePushSubscribe(input: Record<string, unknown>): SchemaResult<{
    endpoint: string;
    keys: { p256dh: string; auth: string };
    locale: string | null;
}> {
    const endpoint = optionalString(input.endpoint);
    if (endpoint == null || endpoint.length > 500 || !/^https?:\/\//i.test(endpoint)) {
        return fail('endpoint', 'Invalid endpoint URL.');
    }
    const keys = input.keys;
    if (keys == null || typeof keys !== 'object') return fail('keys', 'Keys are required.');
    const k = keys as Record<string, unknown>;
    const p256dh = optionalString(k.p256dh);
    const auth = optionalString(k.auth);
    if (p256dh == null || p256dh.length > 255) return fail('keys.p256dh', 'Invalid p256dh key.');
    if (auth == null || auth.length > 255) return fail('keys.auth', 'Invalid auth key.');
    const locale = optionalString(input.locale);
    if (locale != null && locale.length > 16) return fail('locale', 'Invalid locale.');

    return {
        ok: true,
        data: { endpoint, keys: { p256dh, auth }, locale },
    };
}

export function parseAdminLogin(input: Record<string, unknown>): SchemaResult<{
    email: string;
    password: string;
}> {
    const email = optionalString(input.email);
    const password = optionalString(input.password);
    if (email == null || email.length > 255 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return fail('email', 'Valid email is required.');
    }
    if (password == null || password.length < 1 || password.length > 255) {
        return fail('password', 'Password is required.');
    }
    return { ok: true, data: { email, password } };
}

export function parseManualPrice(input: Record<string, unknown>): SchemaResult<{
    company_id: number;
    fuel_type_id: number;
    price: number;
    unit: string | null;
}> {
    const companyId = Number(input.company_id);
    const fuelTypeId = Number(input.fuel_type_id);
    const price = Number(input.price);
    if (!Number.isInteger(companyId) || companyId < 1) return fail('company_id', 'Invalid company.');
    if (!Number.isInteger(fuelTypeId) || fuelTypeId < 1) return fail('fuel_type_id', 'Invalid fuel type.');
    if (!Number.isFinite(price) || price < 1 || price > 9999) return fail('price', 'Price must be 1–9999.');
    const unit = optionalString(input.unit);
    if (unit != null && (unit.length > 16 || !/^[A-Za-z$€£¥₹/\s]{1,16}$/.test(unit))) {
        return fail('unit', 'Invalid unit.');
    }
    return { ok: true, data: { company_id: companyId, fuel_type_id: fuelTypeId, price, unit } };
}

export function parseAdminUser(input: Record<string, unknown>): SchemaResult<{
    name: string;
    email: string;
    password: string;
    is_admin: boolean;
}> {
    const name = optionalString(input.name);
    const email = optionalString(input.email);
    const password = optionalString(input.password);
    if (name == null || name.length > 120) return fail('name', 'Name is required (max 120).');
    if (email == null || email.length > 255 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return fail('email', 'Valid email is required.');
    }
    if (password == null || password.length < 12 || password.length > 255) {
        return fail('password', 'Password must be 12–255 characters.');
    }
    return {
        ok: true,
        data: {
            name,
            email,
            password,
            is_admin: Boolean(input.is_admin),
        },
    };
}

export function parseAdminPassword(input: Record<string, unknown>): SchemaResult<{ password: string }> {
    const password = optionalString(input.password);
    if (password == null || password.length < 12 || password.length > 255) {
        return fail('password', 'Password must be 12–255 characters.');
    }
    return { ok: true, data: { password } };
}

export function toQuery(data: Record<string, string | number | null | undefined>): Record<string, string> {
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(data)) {
        if (v == null || v === '') continue;
        out[k] = String(v);
    }
    return out;
}
