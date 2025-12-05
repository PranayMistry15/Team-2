-- Foreign keys and indexes for integrity and optimization

ALTER TABLE orders
  ADD INDEX idx_orders_user_id (user_id),
  ADD INDEX idx_orders_created_at (created_at),
  ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE order_items
  ADD INDEX idx_order_items_order_id (order_id),
  ADD INDEX idx_order_items_product_id (product_id),
  ADD CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE;

ALTER TABLE cart
  ADD INDEX idx_cart_user_id (user_id),
  ADD INDEX idx_cart_session_id (session_id),
  ADD INDEX idx_cart_product_id (product_id),
  ADD CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  ADD CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE;

ALTER TABLE products
  ADD INDEX idx_products_brand (brand),
  ADD INDEX idx_products_is_featured (is_featured),
  ADD INDEX idx_products_created_at (created_at);

