const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

let authToken: string | null = null;

export function setToken(token: string) {
  authToken = token;
  if (typeof window !== 'undefined') {
    localStorage.setItem('auth_token', token);
  }
}

export function getToken(): string | null {
  if (authToken) return authToken;
  if (typeof window !== 'undefined') {
    authToken = localStorage.getItem('auth_token');
  }
  return authToken;
}

export function clearToken() {
  authToken = null;
  if (typeof window !== 'undefined') {
    localStorage.removeItem('auth_token');
  }
}

async function request<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const token = getToken();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const res = await fetch(`${API_BASE}${endpoint}`, {
    ...options,
    headers,
  });

  const data = await res.json();

  if (!res.ok) {
    throw new Error(data.message || data.error || 'API request failed');
  }

  return data;
}

// Auth
export async function telegramLogin(initData: string) {
  const params = new URLSearchParams();
  if (typeof window !== 'undefined') {
    const WebApp = (await import('@twa-dev/sdk')).default;
    const user = WebApp.initDataUnsafe?.user;
    if (user) {
      params.set('init_data', initData);
      params.set('telegram_id', String(user.id));
      if (user.first_name) params.set('first_name', user.first_name);
      if (user.last_name) params.set('last_name', user.last_name);
      if (user.username) params.set('username', user.username);
      if (user.photo_url) params.set('photo_url', user.photo_url);
    }
  }

  const res = await fetch(`${API_BASE}/auth/telegram`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.fromEntries(params)),
  });

  const data = await res.json();
  if (!res.ok) throw new Error(data.message || 'Auth failed');

  setToken(data.token);
  return data;
}

export async function getMe() {
  return request<{ user: any }>('/auth/me');
}

export async function updateProfile(data: { first_name?: string; last_name?: string; phone?: string; address?: string }) {
  return request<{ message: string; user: any }>('/auth/profile', {
    method: 'PUT',
    body: JSON.stringify(data),
  });
}

// Categories
export async function getCategories() {
  const data = await request<{ categories: any[] }>('/categories');
  return data.categories;
}

// Products
export async function getProducts(params: Record<string, string>) {
  const qs = new URLSearchParams(params).toString();
  return request<any>(`/products?${qs}`);
}

export async function getProduct(slug: string) {
  return request<{ product: any }>(`/products/${slug}`);
}

// Cart
export async function getCart() {
  const data = await request<{ items: any[]; total: number; count: number }>('/cart');
  return data;
}

export async function addToCart(productId: string | number, quantity = 1) {
  return request<{ message: string; item: any }>('/cart', {
    method: 'POST',
    body: JSON.stringify({ product_id: productId, quantity }),
  });
}

export async function updateCartItem(itemId: string | number, quantity: number) {
  return request<{ message: string; item: any }>(`/cart/${itemId}`, {
    method: 'PUT',
    body: JSON.stringify({ quantity }),
  });
}

export async function removeCartItem(itemId: string | number) {
  return request<{ message: string }>(`/cart/${itemId}`, {
    method: 'DELETE',
  });
}

export async function clearCart() {
  return request<{ message: string }>('/cart', { method: 'DELETE' });
}

// Orders
export async function getOrders() {
  const data = await request<{ orders: any[] }>('/orders');
  return data.orders;
}

export async function getOrder(id: string | number) {
  return request<{ order: any }>(`/orders/${id}`);
}

export async function createInvoice(data: { shipping_address?: string; phone?: string; note?: string }) {
  return request<{ invoice_link: string; order_number: string; total_amount: number }>('/create-invoice', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export async function confirmPayment(orderNumber: string, transactionId: string) {
  return request<{ message: string; order: any }>('/payment-success', {
    method: 'POST',
    body: JSON.stringify({ order_number: orderNumber, transaction_id: transactionId }),
  });
}
