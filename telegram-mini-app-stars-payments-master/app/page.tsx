'use client';

import { useEffect, useState, useCallback } from 'react';
import { Screen, User, Category, Product, Order, SortOption, PaginationInfo, Toast } from '@/app/types';
import * as api from '@/lib/api';

import ErrorBoundary from '@/app/components/ErrorBoundary';
import LoadingState from '@/app/components/LoadingState';
import ErrorState from '@/app/components/ErrorState';
import ToastContainer from '@/app/components/Toast';
import AppShell from '@/app/components/layout/AppShell';

import SearchBar from '@/app/components/home/SearchBar';
import CategoryBar from '@/app/components/home/CategoryBar';
import FeaturedCarousel from '@/app/components/home/FeaturedCarousel';
import ProductGrid from '@/app/components/home/ProductGrid';
import ProductDetail from '@/app/components/product/ProductDetail';
import CartScreen from '@/app/components/cart/CartScreen';
import CheckoutForm from '@/app/components/checkout/CheckoutForm';
import OrderHistory from '@/app/components/profile/OrderHistory';
import OrderCard from '@/app/components/orders/OrderCard';
import ReceiptModal from '@/app/components/orders/ReceiptModal';
import ProfileForm from '@/app/components/profile/ProfileForm';

export default function Home() {
  const [initialized, setInitialized] = useState(false);
  const [initError, setInitError] = useState<string | null>(null);
  const [user, setUser] = useState<User | null>(null);

  const [screen, setScreen] = useState<Screen>('home');
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [cartRefreshKey, setCartRefreshKey] = useState(0);

  const [categories, setCategories] = useState<Category[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [productsLoading, setProductsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeCategory, setActiveCategory] = useState('');
  const [sortOption, setSortOption] = useState<SortOption>('newest');
  const [pagination, setPagination] = useState<PaginationInfo | null>(null);
  const [currentPage, setCurrentPage] = useState(1);

  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [cartData, setCartData] = useState<{ total: number; count: number }>({ total: 0, count: 0 });
  const [checkoutLoading, setCheckoutLoading] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<Order | null>(null);
  const [showReceipt, setShowReceipt] = useState(false);

  const addToast = useCallback((type: Toast['type'], message: string) => {
    const id = `toast_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
    setToasts((prev) => [...prev, { id, type, message }]);
  }, []);

  const removeToast = useCallback((id: string) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  useEffect(() => {
    const initApp = async () => {
      try {
        const WebApp = (await import('@twa-dev/sdk')).default;
        const isTelegram = WebApp.isExpanded !== undefined;

        if (!isTelegram) {
          setInitError('This app can only be accessed from within Telegram');
          setInitialized(true);
          return;
        }

        WebApp.ready();
        WebApp.expand();

        if (!WebApp.initDataUnsafe?.user) {
          setInitError('No user data available');
          setInitialized(true);
          return;
        }

        const initData = WebApp.initData || '';
        const result = await api.telegramLogin(initData);
        setUser(result.user);
        setInitialized(true);
      } catch (e) {
        console.error('Init error:', e);
        setInitError('Failed to initialize');
        setInitialized(true);
      }
    };

    initApp();
  }, []);

  useEffect(() => {
    if (!initialized || !user) return;
    fetchCategories();
    fetchProducts();
    fetchCartSummary();
  }, [initialized, user]);

  useEffect(() => {
    if (!initialized || !user) return;
    const timer = setTimeout(() => { setCurrentPage(1); fetchProducts(); }, 300);
    return () => clearTimeout(timer);
  }, [searchQuery, activeCategory, sortOption]);

  useEffect(() => {
    if (!initialized || !user) return;
    fetchProducts();
  }, [currentPage]);

  const fetchCategories = async () => {
    try {
      const data = await api.getCategories();
      setCategories(data || []);
    } catch (e) {
      console.error('Error fetching categories:', e);
    }
  };

  const fetchProducts = async () => {
    setProductsLoading(true);
    try {
      const params: Record<string, string> = {};
      if (searchQuery) params.search = searchQuery;
      if (activeCategory) params.category_id = activeCategory;

      const sortMap: Record<SortOption, { sortBy: string; sortOrder: string }> = {
        'newest': { sortBy: 'created_at', sortOrder: 'desc' },
        'price-asc': { sortBy: 'price', sortOrder: 'asc' },
        'price-desc': { sortBy: 'price', sortOrder: 'desc' },
        'rating': { sortBy: 'rating', sortOrder: 'desc' },
        'popular': { sortBy: 'review_count', sortOrder: 'desc' },
      };
      const s = sortMap[sortOption];
      params.sort = s.sortBy;
      params.order = s.sortOrder;
      params.page = String(currentPage);
      params.per_page = '20';

      const data = await api.getProducts(params);
      setProducts(data.data || []);
      setPagination({
        page: data.current_page || 1,
        limit: data.per_page || 20,
        total: data.total || 0,
        totalPages: data.last_page || 1,
      });
    } catch (e) {
      console.error('Error fetching products:', e);
    } finally {
      setProductsLoading(false);
    }
  };

  const handleProductPress = (product: Product) => {
    setSelectedProduct(product);
    setScreen('product');
  };

  const handleAddToCart = async (product: Product) => {
    if (!user) return;
    try {
      await api.addToCart(product.id, 1);
      addToast('success', `${product.name} added to cart!`);
      setCartRefreshKey((k) => k + 1);
    } catch (e) {
      addToast('error', e instanceof Error ? e.message : 'Failed to add to cart');
    }
  };

  const handleCheckout = async (data: { shippingAddress: string; phone: string; note: string }) => {
    if (!user) return;
    setCheckoutLoading(true);
    try {
      const result = await api.createInvoice({
        shipping_address: data.shippingAddress,
        phone: data.phone,
        note: data.note,
      });

      const WebApp = (await import('@twa-dev/sdk')).default;
      WebApp.openInvoice(result.invoice_link, async (status) => {
        if (status === 'paid') {
          const txnId = `txn_${Date.now()}_${Math.floor(Math.random() * 10000)}`;

          try {
            await api.confirmPayment(result.order_number, txnId);
            addToast('success', `Order ${result.order_number} confirmed!`);
            setCartRefreshKey((k) => k + 1);
            setScreen('orders');
          } catch {
            addToast('error', 'Payment succeeded but order confirmation failed. Contact support.');
          }
        } else if (status === 'failed') {
          addToast('error', 'Payment failed. Please try again.');
        }
        setCheckoutLoading(false);
        setScreen('orders');
      });
    } catch (e) {
      addToast('error', e instanceof Error ? e.message : 'Checkout failed');
      setCheckoutLoading(false);
    }
  };

  const handleOrderPress = (order: Order) => {
    setSelectedOrder(order);
    setScreen('order-detail');
  };

  const fetchCartSummary = async () => {
    if (!user) return;
    try {
      const data = await api.getCart();
      setCartData({ total: data.total, count: data.count });
    } catch {
      setCartData({ total: 0, count: 0 });
    }
  };

  const handleTabChange = (newScreen: Screen) => {
    if (newScreen === 'cart' || newScreen === 'checkout') {
      setCartRefreshKey((k) => k + 1);
      fetchCartSummary();
    }
    setScreen(newScreen);
  };

  if (!initialized) return <LoadingState />;
  if (initError) return <ErrorState error={initError} onRetry={() => window.location.reload()} />;
  if (!user) return <LoadingState />;

  return (
    <ErrorBoundary>
      <AppShell activeScreen={screen} onTabChange={handleTabChange} cartCount={cartData.count}>
        <ToastContainer toasts={toasts} onRemove={removeToast} />

        {showReceipt && selectedOrder && (
          <ReceiptModal order={selectedOrder} onClose={() => setShowReceipt(false)} />
        )}

        {screen === 'home' && (
          <div className="p-4 space-y-4">
            <h1 className="text-2xl font-bold">Sales Man</h1>
            <SearchBar onSearch={setSearchQuery} />
            <FeaturedCarousel products={products} onProductPress={handleProductPress} />
            <CategoryBar
              categories={categories}
              activeCategory={activeCategory}
              onSelect={(id) => setActiveCategory(id)}
            />
            <div className="flex gap-2 overflow-x-auto pb-1">
              {(['newest', 'price-asc', 'price-desc', 'rating', 'popular'] as SortOption[]).map((opt) => (
                <button
                  key={opt}
                  onClick={() => setSortOption(opt)}
                  className={`flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-medium cursor-pointer transition-colors ${
                    sortOption === opt
                      ? 'bg-[var(--tg-theme-button-color)] text-[var(--tg-theme-button-text-color)]'
                      : 'bg-gray-100 dark:bg-gray-700'
                  }`}
                >
                  {opt === 'newest' ? 'Newest' : opt === 'price-asc' ? 'Price: Low' : opt === 'price-desc' ? 'Price: High' : opt === 'rating' ? 'Top Rated' : 'Popular'}
                </button>
              ))}
            </div>
            {productsLoading ? (
              <div className="flex justify-center py-12">
                <div className="spinner" />
              </div>
            ) : (
              <ProductGrid products={products} onProductPress={handleProductPress} />
            )}
            {pagination && pagination.totalPages > 1 && (
              <div className="flex justify-center items-center gap-2 py-4">
                <button
                  onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                  disabled={currentPage <= 1}
                  className="px-3 py-1.5 rounded-lg text-sm tg-button cursor-pointer disabled:opacity-40"
                >
                  ← Prev
                </button>
                <span className="text-xs tg-hint">
                  {pagination.page} / {pagination.totalPages}
                </span>
                <button
                  onClick={() => setCurrentPage((p) => Math.min(pagination.totalPages, p + 1))}
                  disabled={currentPage >= pagination.totalPages}
                  className="px-3 py-1.5 rounded-lg text-sm tg-button cursor-pointer disabled:opacity-40"
                >
                  Next →
                </button>
              </div>
            )}
          </div>
        )}

        {screen === 'product' && selectedProduct && (
          <div className="p-4">
            <ProductDetail
              product={selectedProduct}
              onAddToCart={handleAddToCart}
              onBack={() => {
                setSelectedProduct(null);
                setScreen('home');
              }}
            />
          </div>
        )}

        {screen === 'cart' && (
          <div className="p-4">
            <h2 className="text-xl font-bold mb-4">Shopping Cart</h2>
            <CartScreen
              userId={user.id}
              onCheckout={() => setScreen('checkout')}
              refreshKey={cartRefreshKey}
            />
          </div>
        )}

        {screen === 'checkout' && (
          <div className="p-4">
            <CheckoutForm
              initialAddress={user.address || ''}
              initialPhone={user.phone || ''}
              totalAmount={cartData.total}
              itemCount={cartData.count}
              onSubmit={handleCheckout}
              onBack={() => setScreen('cart')}
              loading={checkoutLoading}
            />
          </div>
        )}

        {screen === 'orders' && (
          <div className="p-4">
            <h2 className="text-xl font-bold mb-4">My Orders</h2>
            <OrderHistory userId={user.id} onOrderPress={handleOrderPress} />
          </div>
        )}

        {screen === 'order-detail' && selectedOrder && (
          <div className="p-4">
            <button
              onClick={() => { setSelectedOrder(null); setScreen('orders'); }}
              className="flex items-center gap-1 text-sm tg-link mb-4 cursor-pointer"
            >
              ← Back to Orders
            </button>
            <OrderCard order={selectedOrder} onPress={() => setShowReceipt(true)} />
            <button
              onClick={() => setShowReceipt(true)}
              className="w-full py-3 rounded-xl tg-button text-base font-semibold mt-4 cursor-pointer"
            >
              View Receipt & Tracking
            </button>
          </div>
        )}

        {screen === 'profile' && (
          <div className="p-4">
            <h2 className="text-xl font-bold mb-6">My Profile</h2>
            <ProfileForm user={user} onSaved={() => {}} />
          </div>
        )}
      </AppShell>
    </ErrorBoundary>
  );
}
