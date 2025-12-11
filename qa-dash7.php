<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$TOOLS_HTML = [
    'add_to_cart' => <<<'ADD_TO_CART_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Add to Cart Checker (Guest & Logged-in)</title>
<style>
  :root{--blue:#007BFF;--bg:#f4f7fa;--card:#fff;--r:10px;--sh:0 4px 12px rgba(0,0,0,.1)}
  *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
  body{background:var(--bg);display:flex;align-items:center;justify-content:center;min-height:100vh}
  .container{width:95%;max-width:980px;background:var(--card);padding:20px;border-radius:var(--r);box-shadow:var(--sh)}
  header{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:18px}
  header img{width:50px;height:50px} header h1{font-size:24px;color:var(--blue);margin:0}
  fieldset{border:1px solid #ddd;border-radius:8px;padding:14px;margin-bottom:14px}
  legend{padding:0 6px;color:#333;font-weight:700}
  label{display:block;margin:6px 0}
  textarea, input[type="text"], input[type="number"]{width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:15px}
  textarea{min-height:120px;resize:vertical}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
  button{background:var(--blue);color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer;font-size:15px;font-weight:600;transition:.25s}
  button:hover{background:#005fd1}
  .alt{background:#555}.alt:hover{background:#444}
  .copy{background:#00906d}.copy:hover{background:#007a5c}
  #loading{display:none;color:#f90;font-weight:700;margin-top:6px}
  .output{margin-top:14px;padding:16px;background:#f9f9f9;border:1px solid #ddd;border-radius:8px}
  ul{list-style:none;padding-left:0;margin:0}
  li{padding:10px 0;border-bottom:1px solid #e1e1e1;line-height:1.35}
  li:last-child{border-bottom:none}
  .chip{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;margin-left:6px;border-radius:12px;vertical-align:middle;letter-spacing:.4px}
  .ok{color:#096b09;background:#d5f7d5}
  .warn{color:#8d4a00;background:#ffe3c4}
  .err{color:#a10000;background:#ffd4d4}
  .meta{font-size:12.5px;color:#555;margin-top:4px;word-break:break-all}
  .hint{font-size:12.5px;color:#666;margin-top:8px}
  .country-grid{display:flex;flex-wrap:wrap;gap:12px}
  .country-grid label{display:flex;align-items:center;gap:8px;border:1px solid #ddd;border-radius:6px;padding:6px 10px;background:#fff;cursor:pointer}
  .inline{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
  .hidden{display:none}
  @media(max-width:720px){.row{grid-template-columns:1fr}}

  .run-details{margin-top:16px;border-top:1px solid #e2e6ee;padding-top:12px;}
  .run-details h3{margin-bottom:8px;font-size:15px;}
  .run-details-content{max-height:260px;overflow:auto;font-size:13px;background:#f8fafc;border-radius:8px;padding:8px 10px;border:1px solid #e2e6ee;}
  .run-details-table{width:100%;border-collapse:collapse;font-size:12px;}
  .run-details-table th,.run-details-table td{border-bottom:1px solid #e2e6ee;padding:4px 6px;text-align:left;white-space:nowrap;}
  .run-details-badge{display:inline-block;padding:2px 6px;border-radius:999px;font-size:11px;font-weight:600;}
  .run-details-badge.ok{background:#e3f2fd;color:#0d47a1;}
  .run-details-badge.fail{background:#ffebee;color:#b71c1c;}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>Add to Cart Checker</h1>
  </header>

  <fieldset>
    <legend>Mode</legend>
    <div class="inline">
      <label class="inline"><input type="radio" name="mode" value="guest" checked> Guest</label>
      <label class="inline"><input type="radio" name="mode" value="loggedin"> Logged-in (login per country)</label>
    </div>
  </fieldset>

  <fieldset id="loginBox" class="hidden">
    <legend>Login Credentials (single JSON object)</legend>
    <label for="loginJson">Example:
      <code>{"username":"0533645794","password":"Jarir13245!"}</code>
    </label>
    <textarea id="loginJson" placeholder='{"username":"0533645794","password":"Jarir13245!"}'></textarea>
    <div class="hint">We’ll call <code>/api/v2/&lt;store&gt;/user/login-v2</code> for each selected country and use <code>token</code> + <code>quote_id</code> returned for that country.</div>
  </fieldset>

  <fieldset>
    <legend>Countries</legend>
    <div class="country-grid">
      <label><input type="checkbox" class="country" value="SA" checked> Saudi Arabia (SA)</label>
      <label><input type="checkbox" class="country" value="AE" checked> United Arab Emirates (AE)</label>
      <label><input type="checkbox" class="country" value="KW" checked> Kuwait (KW)</label>
      <label><input type="checkbox" class="country" value="QA" checked> Qatar (QA)</label>
      <label><input type="checkbox" class="country" value="BH" checked> Bahrain (BH)</label>
    </div>
    <div class="hint">Multiple selection supported—each country is processed independently.</div>
  </fieldset>

  <fieldset>
    <legend>SKUs</legend>
    <label for="skus">Enter SKUs (one per line):</label>
    <textarea id="skus" placeholder="642528
650944
623548"></textarea>
    <div class="row">
      <div>
        <label for="qty">Default Qty (applied to all SKUs):</label>
        <input type="number" id="qty" min="1" value="1">
      </div>
      <div class="hint">We use <code>updateMultiple</code> with these SKUs bundled per country.</div>
    </div>
  </fieldset>

  <div class="actions">
    <button onclick="run()">Run Add to Cart</button>
    <button class="alt" onclick="clearSession()">Clear</button>
    <button class="copy" onclick="copyVisible()">Copy Visible Results</button>
  </div>
  <div id="loading">Processing… please wait</div>

  <div class="output">
    <ul id="results"></ul>
    <p id="empty" style="display:none;color:#c00;font-weight:600">No results to show.</p>
    <div class="hint">
      <strong>Guest:</strong> <code>POST /api/v2/&lt;store&gt;/cart/createv2</code> → use <code>data.result</code> as <code>quoteId</code> in <code>updateMultiple</code> (no token).<br>
      <strong>Logged-in:</strong> <code>POST /api/v2/&lt;store&gt;/user/login-v2</code> → use <code>token</code> header <code>currenttoken</code> and <code>quote_id</code>.
    </div>
  </div>
</div>

<script>
/* ---------- Country → store + code mapping ---------- */
const COUNTRY_MAP = {
  SA: {store:'sa_en', code:'SA'},
  AE: {store:'ae_en', code:'AE'},
  KW: {store:'kw_en', code:'KW'},
  QA: {store:'qa_en', code:'QA'},
  BH: {store:'bh_en', code:'BH'}
};

const LOGIN_API   = (store)=>`https://www.jarir.com/api/v2/${store}/user/login-v2`;
const CART_API    = (store)=>`https://www.jarir.com/api/v2/${store}/cart/updateMultiple`;
const CREATE_GUEST= (store)=>`https://www.jarir.com/api/v2/${store}/cart/createv2`;

/* ---------- State ---------- */
const rows = window.rows = []; // {country, store, status, ok, message, details, url, parent}

/* ---------- UI Wiring ---------- */
document.querySelectorAll('input[name="mode"]').forEach(r=>{
  r.addEventListener('change', ()=>{
    document.getElementById('loginBox')
      .classList.toggle('hidden', getMode()!=='loggedin');
  });
});

function getMode(){ return document.querySelector('input[name="mode"]:checked').value; }

/* ---------- Main ---------- */
async function run(){
  toggleLoading(true);
  clearResults();

  const selected = [...document.querySelectorAll('.country:checked')].map(i=>i.value);
  if(!selected.length){ alert('Please select at least one country.'); toggleLoading(false); return; }

  const qty = Math.max(1, parseInt(document.getElementById('qty').value||'1',10));
  const skus = document.getElementById('skus').value.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  if(!skus.length){ alert('Please enter at least one SKU.'); toggleLoading(false); return; }
  const skusArray = skus.map(s=>({sku:s, qty}));

  let creds=null;
  if(getMode()==='loggedin'){
    const raw = document.getElementById('loginJson').value.trim();
    if(!raw){ alert('Please paste login JSON.'); toggleLoading(false); return; }
    try{
      const parsed = JSON.parse(raw);
      const username=(parsed?.username??'').toString().trim();
      const password=(parsed?.password??'').toString();
      if(!username || !password){ throw new Error('Login JSON must include "username" and "password".'); }
      creds={username,password};
    }catch(e){
      alert('Invalid login JSON: '+e.message);
      toggleLoading(false);
      return;
    }
  }

  // Process each country independently
  for(const country of selected){
    const map = COUNTRY_MAP[country];
    if(!map){ pushRow({country, store:'?', ok:false, status:'ERROR', message:'Unknown country mapping', details:{}, url:'?', parent:'?'}); render(); continue; }

    let token='', quoteId=null;

    if(getMode()==='loggedin'){
      // country-specific login
      try{
        const loginResp = await fetch(LOGIN_API(map.store),{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({username:creds.username, password:creds.password})
        }).then(r=>r.json());

        const success = loginResp?.success===true && !!(loginResp?.data?.token);
        if(!success){
          pushRow({country, store:map.store, ok:false, status:'ERROR',
            message:`Login failed: ${loginResp?.message||'Unknown'} (${loginResp?.type||''})`, details:loginResp, url:'Login', parent:map.store});
          render();
          continue; // Skip A2C for this country but continue others
        }
        token   = loginResp.data.token || '';
        quoteId = loginResp.data.quote_id ?? null;
        pushRow({country, store:map.store, ok:true, status:'OK', message:'Login success', details:{token,quoteId}, url:'Login', parent:map.store});
        render();
      }catch(e){
        pushRow({country, store:map.store, ok:false, status:'ERROR', message:'Login request failed: '+e.message, details:{}, url:'Login', parent:map.store});
        render();
        continue;
      }
    }else{
      // Guest: create guest quote first (per country)
      try{
        const guestResp = await fetch(CREATE_GUEST(map.store),{
          method:'POST'
        }).then(r=>r.json());
        const resultId = guestResp?.data?.result || '';
        if(!resultId){
          pushRow({country, store:map.store, ok:false, status:'ERROR', message:'Guest quote creation failed', details:guestResp, url:'GuestQuote', parent:map.store});
          render();
          continue;
        }
        quoteId = resultId;
        pushRow({country, store:map.store, ok:true, status:'OK', message:'Guest quote created', details:{quoteId}, url:'GuestQuote', parent:map.store});
        render();
      }catch(e){
        pushRow({country, store:map.store, ok:false, status:'ERROR', message:'Guest quote request failed: '+e.message, details:{}, url:'GuestQuote', parent:map.store});
        render();
        continue;
      }
    }

    // Prepare add-to-cart payload
    const body = {
      cartItem: {
        skus: skusArray,
        extension_attributes: { country_code: map.code }
      }
    };
    // Both flows now require quoteId (guest uses createv2 result; logged-in uses login quote_id)
    if(quoteId!=null){ body.cartItem.quoteId = quoteId; }

    const headers = {'Content-Type':'application/json'};
    if(getMode()==='loggedin' && token){
      headers['currenttoken'] = token;
    }

    // Call updateMultiple
    try{
      const resp = await fetch(CART_API(map.store),{
        method:'POST', headers, body: JSON.stringify(body)
      });
      const data = await resp.json().catch(()=>({}));
      const ok = data?.success===true;
      const status = ok ? 'OK' : (resp.ok ? 'WARN' : 'ERROR');
      const message = (data?.message || (ok?'Added to cart':'Not added')).toString();
      pushRow({country, store:map.store, ok, status, message, details:data, url:'SKUs: '+skusArray.length, parent:map.store});
      render();
    }catch(e){
      pushRow({country, store:map.store, ok:false, status:'ERROR', message:'Add to cart failed: '+e.message, details:{}, url:'SKUs: '+skusArray.length, parent:map.store});
      render();
    }
  }

  toggleLoading(false);
}

/* ---------- Rows / Render ---------- */
function pushRow({country,store,ok,status,message,details,url,parent}){
  rows.push({country, store, ok, status, message, details, url, parent});
}

function render(){
  const ul=document.getElementById('results'); ul.innerHTML='';
  rows.forEach(r=>{
    const cls = r.status==='OK' ? 'ok' : (r.status==='WARN' ? 'warn' : 'err');
    const pretty = safeStr(r.details);
    const li = document.createElement('li');
    li.className = cls;
    li.innerHTML = `
      <strong>${r.country}</strong> → Store: <code>${r.store}</code>
      <span class="chip ${cls}">${r.status}</span>
      <div class="meta">
        ${escapeHtml(r.message)}<br>
        <details style="margin-top:6px"><summary>Response</summary><pre style="white-space:pre-wrap">${escapeHtml(pretty)}</pre></details>
      </div>
    `;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = rows.length ? 'none':'block';
}

function clearResults(){
  rows.length = 0;
  document.getElementById('results').innerHTML='';
  document.getElementById('empty').style.display='none';
}

function clearSession(){
  clearResults();
  document.getElementById('skus').value='';
  document.getElementById('qty').value='1';
  document.getElementById('loginJson').value='';
  document.querySelector('input[name="mode"][value="guest"]').checked = true;
  document.getElementById('loginBox').classList.add('hidden');
  document.querySelectorAll('.country').forEach(el=>el.checked = true);
  toggleLoading(false);
}

function copyVisible(){
  const items=[...document.querySelectorAll('#results li')].map(li=>li.innerText.trim());
  if(!items.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(items.join('\n\n')).then(()=>alert('Copied!'));
}

function toggleLoading(on){ document.getElementById('loading').style.display = on ? 'block' : 'none'; }

/* ---------- utils ---------- */
function safeStr(o){ try{return JSON.stringify(o,null,2);}catch{return String(o);} }
function escapeHtml(s){ return (s||'').toString().replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
</script>
</body>
</html>

ADD_TO_CART_HTML,
    'brand' => <<<'BRAND_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Brand Link Checker</title>
<style>
 :root{
   --blue:#007BFF;
   --bg:#f4f7fa;
   --card:#fff;
   --radius:10px;
   --shadow:0 4px 12px rgba(0,0,0,.1);
   --ok:#2e7d32;
   --warn:#d84315;
   --err:#b00020;
   --badge-bg:#eceff1;
   --mono: "SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);
   min-height:100vh;
   display:flex;
   align-items:center;
   justify-content:center;
   padding:18px;
   color:#222;
 }
 .container{
   width:100%;max-width:960px;
   background:var(--card);
   border-radius:var(--radius);
   box-shadow:var(--shadow);
   padding:24px 26px 30px;
   display:flex;
   flex-direction:column;
   gap:14px;
 }
 header{
   display:flex;
   align-items:center;
   justify-content:space-between;
   padding-bottom:12px;
   border-bottom:2px solid var(--blue);
 }
 header h1{font-size:24px;color:var(--blue);margin:0}
 header img{width:54px;height:54px}

 textarea{
   width:100%;height:150px;
   padding:12px 14px;
   border:1px solid #cbd3dc;
   border-radius:8px;
   font-size:15px;
   resize:vertical;
 }
 .controls{
   display:flex;
   flex-wrap:wrap;
   gap:10px;
 }
 button{
   background:var(--blue);
   border:none;
   color:#fff;
   padding:10px 18px;
   font-size:15px;
   border-radius:7px;
   cursor:pointer;
   line-height:1.1;
   display:inline-flex;
   align-items:center;
   gap:6px;
   transition:.25s;
 }
 button:hover{background:#005ed0}
 button.secondary{background:#546e7a}
 button.secondary:hover{background:#455a64}
 button.copy{background:#00906d}
 button.copy:hover{background:#007354}

 #loading{display:none;font-weight:600;color:#f90}

 .panel{
   margin-top:4px;
   padding:20px 20px 26px;
   background:#f9fafb;
   border:1px solid #dfe5eb;
   border-radius:10px;
 }
 .panel h3{margin-top:0;margin-bottom:14px;font-size:20px;color:#333}

 .filters{
   display:flex;
   flex-wrap:wrap;
   gap:14px;
   margin-bottom:10px;
   align-items:flex-end;
 }
 .filters label{
   font-size:14px;
   font-weight:600;
   color:#333;
   display:flex;
   flex-direction:column;
   gap:6px;
 }
 select,input[type=text]{
   min-width:210px;
   padding:8px 10px;
   border:1px solid #bfc9d3;
   border-radius:6px;
   font-size:14px;
   background:#fff;
 }
 input::placeholder{color:#999}

 ul#results{list-style:none;margin:0;padding:0}
 ul#results li{
   padding:10px 10px 12px;
   border-bottom:1px solid #e3e8ee;
   display:flex;
   flex-direction:column;
   gap:4px;
   font-size:14px;
 }
 ul#results li:last-child{border-bottom:none}

 a{color:#0d47a1;text-decoration:none;word-break:break-all}
 a:hover{text-decoration:underline}

 .status-badge{
   display:inline-block;
   font-size:11px;
   letter-spacing:.5px;
   font-weight:700;
   padding:3px 8px 4px;
   border-radius:100px;
   background:var(--badge-bg);
   color:#37474f;
   vertical-align:middle;
   font-family:var(--mono);
 }
 .ok .status-badge{background:var(--ok);color:#fff}
 .warn .status-badge{background:var(--warn);color:#fff}
 .err  .status-badge{background:var(--err);color:#fff}

 .meta-line{font-size:12px;color:#555}
 .empty-msg{color:#d32f2f;font-weight:600;margin:6px 4px 2px}

 .legend{
   margin-top:18px;
   font-size:12.5px;
   line-height:1.5;
   color:#455a64;
   display:flex;
   flex-direction:column;
   gap:4px;
 }
 .legend strong{font-weight:600}
 .legend .row{
   display:flex;
   align-items:center;
   gap:8px;
 }
 footer{margin-top:22px;text-align:center;font-size:13px;color:#888}

 @media(max-width:620px){
   .filters label{width:100%}
   select,input[type=text]{min-width:unset;width:100%}
   button{flex:1 1 auto}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>Brand Link Checker</h1>
  </header>

  <label for="urlInput" style="font-weight:600;font-size:14px;">Enter page-v2 URLs (one per line):</label>
  <textarea id="urlInput" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/123"></textarea>

  <div class="controls">
    <button onclick="run()">Fetch JSON & Generate Links</button>
    <button class="secondary" onclick="clearSession()">Clear Session</button>
    <button class="copy" onclick="copyVisible()">Copy Visible Links</button>
  </div>

  <div id="loading">Processing… please wait</div>

  <div class="panel">
    <h3>Generated Links</h3>
    <div class="filters">
      <label>
        Status Filter
        <select id="statusFilter" onchange="applyFilters()">
          <option value="all">Show All</option>
          <option value="OK">OK</option>
          <option value="Warning">Warning</option>
        </select>
      </label>
      <label>
        Parent URL Filter
        <input id="parentFilter" type="text" placeholder="part of parent URL" oninput="applyFilters()">
      </label>
    </div>

    <ul id="results"></ul>
    <p id="empty" class="empty-msg" style="display:none;">No brand links found for current filters.</p>

    <div class="legend">
      <div class="row"><span class="status-badge ok">OK</span> <strong>OK</strong> – Hits found (products returned).</div>
      <div class="row"><span class="status-badge warn">WARN</span> <strong>Warning</strong> – No hits returned (empty results).</div>
      <div class="row"><span class="status-badge err">ERR</span> <strong>Error</strong> – Fetch / parse problem (shown only in “Show All”).</div>
    </div>
  </div>

  <footer>© 2024 Brand Link Checker</footer>
</div>

<script>
/* ───────── State ───────── */
const rows=[];              // {link,parent,status}
const processed=new Set();  // track fetched parent URLs
const generated=new Set();  // track generated brand URLs

/* ───────── Main Runner ───────── */
async function run(){
  setLoading(true);
  const input=document.getElementById('urlInput').value.trim();
  const parents=input.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  if(!parents.length){alert('Please enter at least one valid URL.');setLoading(false);return;}

  for(const parent of parents){
    if(processed.has(parent)) continue;
    processed.add(parent);
    try{
      const res=await fetch(parent);
      if(!res.ok) throw new Error('Network response not ok');
      const json=await res.json();
      const brands=extractBrands(json);
      const {baseUrl1,store}=splitBase(parent);
      const links=brands.map(b=>`${baseUrl1}catalogv1/product/store/${store}/brand/${b}/size/20/sort-priority/asc/visibilityAll/true`);

      links.forEach(link=>{
        if(generated.has(link)) return;
        generated.add(link);
        rows.push({link,parent,status:'PENDING'});
      });

    }catch(e){
      // record a synthetic error row referencing parent (no link)
      rows.push({link:`(Error loading parent) ${parent}`,parent,status:'Error'});
      console.error(e);
    }
  }

  // Validate brand links
  await validate();
  setLoading(false);
  render();
  applyFilters();
}

/* ───────── Helpers ───────── */
function splitBase(u){
  const baseUrl1=u.split('/api')[0]+'/api/';
  const store=u.split('/')[5].replace('_','-');
  return {baseUrl1,store};
}

function extractBrands(data){
  const items=data?.data?.cms_items?.items||[];
  const brands=[];
  items.forEach(({item})=>{
    item.split('||').forEach(entry=>{
      const parts=entry.split(',');
      if(parts[1]==='brand' && parts[2]){
        brands.push(parts[2]);
      }
    });
  });
  return [...new Set(brands)]; // unique
}

async function validate(){
  for(const row of rows){
    if(row.status!=='PENDING') continue;
    // skip synthetic error lines
    if(!row.link.startsWith('http')){continue;}
    try{
      const r=await fetch(row.link);
      if(!r.ok){row.status='Error';continue;}
      const j=await r.json();
      const hits=j?.hits?.hits || j?.data?.hits?.hits || [];
      row.status=hits.length>0?'OK':'Warning';
    }catch{
      row.status='Error';
    }
  }
}

/* ───────── UI ───────── */
function setLoading(on){document.getElementById('loading').style.display=on?'block':'none';}

function render(){
  const ul=document.getElementById('results');
  ul.innerHTML='';
  rows.forEach(({link,parent,status})=>{
    const li=document.createElement('li');
    li.dataset.status=status;
    li.dataset.parent=parent.toLowerCase();
    li.className=status==='OK'?'ok':(status==='Warning'?'warn':'err');
    if(link.startsWith('http')){
      li.innerHTML=`
        <div><a href="${link}" target="_blank">${link}</a> <span class="status-badge ${li.className==='ok'?'ok':(li.className==='warn'?'warn':'err')}">${status.toUpperCase()}</span></div>
        <div class="meta-line">Parent: ${parent}</div>
      `;
    }else{
      li.innerHTML=`<div>${link} <span class="status-badge err">ERROR</span></div>`;
    }
    ul.appendChild(li);
  });
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const pterm=document.getElementById('parentFilter').value.trim().toLowerCase();
  let visible=0;

  document.querySelectorAll('#results li').forEach(li=>{
    const status=li.dataset.status;
    const parent=li.dataset.parent||'';
    const show =
      (want==='all' || status===want) &&
      (pterm==='' || parent.includes(pterm));
    li.style.display=show?'':'none';
    if(show) visible++;
  });

  document.getElementById('empty').style.display=visible? 'none':'block';
}

function copyVisible(){
  const links=[...document.querySelectorAll('#results li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>{
      const a=li.querySelector('a');
      return a ? a.href : '';
    })
    .filter(Boolean)
    .join('\n');
  if(!links){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links).then(()=>alert('Copied!'));
}

function clearSession(){
  rows.length=0;
  processed.clear();
  generated.clear();
  document.getElementById('urlInput').value='';
  document.getElementById('results').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  setLoading(false);
}

/* initial empty render */
render();
</script>
<script>
  window.QA_TOOL_ID = 'brand';
  window.run = window.run || window.runPairs; // alias
</script>
<script src="qa_bridge.js"></script>
</body>
</html>

BRAND_HTML,
    'category' => <<<'CATEGORY_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Category Link Checker</title>
<style>
 :root{
   --blue:#007BFF;
   --bg:#f4f7fa;
   --card:#fff;
   --radius:10px;
   --shadow:0 4px 12px rgba(0,0,0,.1);
   --ok:#2e7d32;
   --warn:#d84315;
   --warnNT:#ef6c00;
   --err:#b00020;
   --badge-bg:#eceff1;
   --mono:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);
   min-height:100vh;
   display:flex;
   align-items:center;
   justify-content:center;
   padding:18px;
   color:#222;
 }
 .container{
   width:100%;max-width:960px;
   background:var(--card);
   border-radius:var(--radius);
   box-shadow:var(--shadow);
   padding:24px 26px 30px;
   display:flex;
   flex-direction:column;
   gap:14px;
 }
 header{
   display:flex;
   align-items:center;
   justify-content:space-between;
   padding-bottom:12px;
   border-bottom:2px solid var(--blue);
 }
 header h1{font-size:24px;color:var(--blue);margin:0}
 header img{width:54px;height:54px}

 textarea{
   width:100%;height:150px;
   padding:12px 14px;
   border:1px solid #cbd3dc;
   border-radius:8px;
   font-size:15px;
   resize:vertical;
 }

 .controls{display:flex;flex-wrap:wrap;gap:10px}
 button{
   background:var(--blue);
   border:none;
   color:#fff;
   padding:10px 18px;
   font-size:15px;
   border-radius:7px;
   cursor:pointer;
   line-height:1.1;
   display:inline-flex;
   align-items:center;
   gap:6px;
   transition:.25s;
 }
 button:hover{background:#005ed0}
 button.secondary{background:#546e7a}
 button.secondary:hover{background:#455a64}
 button.copy{background:#00906d}
 button.copy:hover{background:#007354}

 #loading{display:none;font-weight:600;color:#f90}

 .panel{
   margin-top:4px;
   padding:20px 20px 26px;
   background:#f9fafb;
   border:1px solid #dfe5eb;
   border-radius:10px;
 }
 .panel h3{margin-top:0;margin-bottom:14px;font-size:20px;color:#333}

 .filters{
   display:flex;
   flex-wrap:wrap;
   gap:14px;
   margin-bottom:10px;
   align-items:flex-end;
 }
 .filters label{
   font-size:14px;
   font-weight:600;
   color:#333;
   display:flex;
   flex-direction:column;
   gap:6px;
 }
 select,input[type=text]{
   min-width:210px;
   padding:8px 10px;
   border:1px solid #bfc9d3;
   border-radius:6px;
   font-size:14px;
   background:#fff;
 }
 input::placeholder{color:#999}

 ul#results{list-style:none;margin:0;padding:0}
 ul#results li{
   padding:10px 10px 12px;
   border-bottom:1px solid #e3e8ee;
   display:flex;
   flex-direction:column;
   gap:4px;
   font-size:14px;
 }
 ul#results li:last-child{border-bottom:none}

 a{color:#0d47a1;text-decoration:none;word-break:break-all}
 a:hover{text-decoration:underline}

 .status-badge{
   display:inline-block;
   font-size:11px;
   letter-spacing:.5px;
   font-weight:700;
   padding:3px 8px 4px;
   border-radius:100px;
   background:var(--badge-bg);
   color:#37474f;
   vertical-align:middle;
   font-family:var(--mono);
 }
 .ok    .status-badge{background:var(--ok);color:#fff}
 .warn  .status-badge{background:var(--warn);color:#fff}
 .warnnt.status-badge,
 .warnnt .status-badge{background:var(--warnNT);color:#fff}
 .err   .status-badge{background:var(--err);color:#fff}

 .meta-line{font-size:12px;color:#555}
 .empty-msg{color:#d32f2f;font-weight:600;margin:6px 4px 2px}

 .legend{
   margin-top:18px;
   font-size:12.5px;
   line-height:1.5;
   color:#455a64;
   display:flex;
   flex-direction:column;
   gap:4px;
 }
 .legend strong{font-weight:600}
 .legend .row{
   display:flex;
   align-items:center;
   gap:8px;
 }

 footer{margin-top:22px;text-align:center;font-size:13px;color:#888}

 @media(max-width:620px){
   .filters label{width:100%}
   select,input[type=text]{min-width:unset;width:100%}
   button{flex:1 1 auto}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>Category Link Checker</h1>
  </header>

  <label for="urlInput" style="font-weight:600;font-size:14px;">Enter page-v2 URLs (one per line):</label>
  <textarea id="urlInput" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/123"></textarea>

  <div class="controls">
    <button onclick="run()">Fetch JSON & Generate Links</button>
    <button class="secondary" onclick="clearSession()">Clear Session</button>
    <button class="copy" onclick="copyVisible()">Copy Visible Links</button>
  </div>

  <div id="loading">Processing… please wait</div>

  <div class="panel">
    <h3>Generated Links</h3>
    <div class="filters">
      <label>
        Status Filter
        <select id="statusFilter" onchange="applyFilters()">
          <option value="all">Show All</option>
          <option value="OK">OK</option>
          <option value="Warning">Warning</option>
          <option value="WarningNT">Warning NT</option>
        </select>
      </label>
      <label>
        Parent URL Filter
        <input id="parentFilter" type="text" placeholder="part of parent URL" oninput="applyFilters()">
      </label>
    </div>

    <ul id="results"></ul>
    <p id="empty" class="empty-msg" style="display:none;">No category links found for current filters.</p>

    <div class="legend">
      <div class="row"><span class="status-badge ok">OK</span> <strong>OK</strong> – Category returns products (hits > 0).</div>
      <div class="row"><span class="status-badge warn">WARN</span> <strong>Warning</strong> – Category returns no products (empty hits).</div>
      <div class="row"><span class="status-badge warnnt">W‑NT</span> <strong>Warning NT</strong> – *Warning* **with numeric category ID only** (subset of Warning).</div>
      <div class="row"><span class="status-badge err">ERR</span> <strong>Error</strong> – Fetch / parse problem (shown only under “Show All”).</div>
    </div>
  </div>

  <footer>© 2024 Category Link Checker</footer>
</div>

<script>
/* ───────── State ───────── */
const rows=[];               // {link,parent,status,isNumeric}
const processedParents=new Set();
const generatedLinks=new Set();

/* ───────── Runner ───────── */
async function run(){
  setLoading(true);
  const raw=document.getElementById('urlInput').value.trim();
  const parents=raw.split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  if(!parents.length){alert('Please enter at least one valid URL.');setLoading(false);return;}

  for(const parent of parents){
    if(processedParents.has(parent)) continue;
    processedParents.add(parent);
    try{
      const r=await fetch(parent);
      if(!r.ok) throw new Error('Network response not ok');
      const j=await r.json();
      const catIds=extractCategories(j);
      const {baseUrl1,store}=splitBase(parent);

      const links=catIds.map(id=>`${baseUrl1}catalogv1/category/store/${store}/category_ids/${id}`);

      links.forEach(l=>{
        if(generatedLinks.has(l)) return;
        generatedLinks.add(l);
        const idMatch=l.match(/\/category_ids\/([^/]+)/i);
        const catId=idMatch?decodeURIComponent(idMatch[1]):'';
        const isNumeric=/^\d+$/.test(catId);
        rows.push({link:l,parent,status:'PENDING',isNumeric});
      });

    }catch(e){
      rows.push({link:`(Error loading parent) ${parent}`,parent,status:'Error',isNumeric:false});
      console.error(e);
    }
  }

  await validate();
  setLoading(false);
  render();
  applyFilters();
}

/* ───────── Helpers ───────── */
function splitBase(u){
  const baseUrl1=u.split('/api')[0]+'/api/';
  const store=u.split('/')[5].replace('_','-');
  return {baseUrl1,store};
}

/* Extract categories (legacy + new style, | separated, unique) */
function extractCategories(json){
  const items=json?.data?.cms_items?.items||[];
  const found=[];
  items.forEach(({item})=>{
    const tokens=item.split('||');

    // NEW STYLE: token == 'category'
    tokens.forEach((tok,i)=>{
      if(tok.trim().toLowerCase()==='category' && tokens[i+1]){
        tokens[i+1].split('|').forEach(id=>{
          id=id.trim();
          if(id) found.push(id);
        });
      }
    });

    // LEGACY segments containing ",category,<ids>"
    tokens.forEach(seg=>{
      const parts=seg.split(',');
      const idx=parts.indexOf('category');
      if(idx!==-1 && parts[idx+1]){
        parts[idx+1].split('|').forEach(id=>{
          id=id.trim();
          if(id) found.push(id);
        });
      }
    });
  });
  return [...new Set(found)];
}

async function validate(){
  for(const row of rows){
    if(row.status!=='PENDING') continue;
    if(!row.link.startsWith('http')) continue;
    try{
      const r=await fetch(row.link);
      if(!r.ok){row.status='Error';continue;}
      const j=await r.json();
      const hits=j?.hits?.hits || j?.data?.hits?.hits || [];
      row.status=hits.length>0?'OK':'Warning';
    }catch{row.status='Error';}
  }
}

/* ───────── UI ───────── */
function setLoading(on){document.getElementById('loading').style.display=on?'block':'none';}

function render(){
  const ul=document.getElementById('results');
  ul.innerHTML='';
  rows.forEach(({link,parent,status,isNumeric})=>{
    const li=document.createElement('li');
    li.dataset.status=status;
    li.dataset.parent=parent.toLowerCase();
    li.dataset.numeric=isNumeric?'1':'0';
    let badgeClass='status-badge';
    let rowClass='';
    if(status==='OK'){rowClass='ok';}
    else if(status==='Warning'){rowClass=isNumeric?'warnnt':'warn';}
    else if(status==='Error'){rowClass='err';}

    if(link.startsWith('http')){
      li.innerHTML=`
        <div><a href="${link}" target="_blank">${link}</a>
          <span class="${badgeClass} ${rowClass==='ok'?'ok':rowClass==='warnnt'?'warnnt':rowClass==='warn'?'warn':rowClass==='err'?'err':''}">
            ${status==='Warning' && isNumeric?'W-NT':status.toUpperCase()}
          </span>
        </div>
        <div class="meta-line">Parent: ${parent}</div>`;
    }else{
      li.innerHTML=`<div>${link} <span class="${badgeClass} err">ERROR</span></div>`;
    }
    li.className=rowClass;
    ul.appendChild(li);
  });
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const pterm=document.getElementById('parentFilter').value.trim().toLowerCase();
  let visible=0;
  document.querySelectorAll('#results li').forEach(li=>{
    const status=li.dataset.status;
    const parent=li.dataset.parent||'';
    const isNum=li.dataset.numeric==='1';

    let show=false;
    if(want==='all') show=true;
    else if(want==='OK' && status==='OK') show=true;
    else if(want==='Warning' && status==='Warning') show=true;
    else if(want==='WarningNT' && status==='Warning' && isNum) show=true;

    if(show && pterm && !parent.includes(pterm)) show=false;

    li.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display=visible?'none':'block';
}

function copyVisible(){
  const text=[...document.querySelectorAll('#results li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>{const a=li.querySelector('a');return a?a.href:'';})
    .filter(Boolean)
    .join('\n');
  if(!text){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(text).then(()=>alert('Copied!'));
}

function clearSession(){
  rows.length=0;
  processedParents.clear();
  generatedLinks.clear();
  document.getElementById('urlInput').value='';
  document.getElementById('results').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  setLoading(false);
}

/* initial blank render */
render();
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='category'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

CATEGORY_HTML,
    'category_filter' => <<<'CATEGORY_FILTER_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Filtered-Category Link Checker</title>
<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --radius:10px;--ok:#096b09;--warn:#8d4a00;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
   padding:18px 10px;
 }
 .container{
   width:100%;max-width:980px;background:var(--card);
   border-radius:var(--radius);padding:22px 24px 26px;
   box-shadow:var(--shadow);
 }
 header{display:flex;align-items:center;justify-content:space-between;
        border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:20px}
 header img{width:54px;height:54px}
 header h1{font-size:24px;color:var(--blue);margin:0;font-weight:600}
 label.top-lbl{font-weight:600;font-size:14px;display:block}
 textarea{
   width:100%;height:140px;margin:14px 0 16px;
   padding:10px 12px;border:1px solid #c7cdd4;border-radius:6px;font-size:15px;
   resize:vertical;line-height:1.35;
 }
 .actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:4px}
 button{
   background:var(--blue);color:#fff;border:none;
   padding:10px 16px;border-radius:6px;cursor:pointer;
   font-size:15px;font-weight:600;letter-spacing:.2px;
   transition:background .25s;
 }
 button:hover{background:#0069d6}
 button.secondary{background:#555}
 button.secondary:hover{background:#333}
 button.success{background:#00906d}
 button.success:hover{background:#007256}
 #loading{display:none;color:#ff9800;font-weight:700;margin:4px 0 10px}
 .output{
   margin-top:4px;padding:18px 18px 16px;background:#f9f9f9;
   border:1px solid #d9dfe5;border-radius:10px;
 }
 .filters{display:flex;flex-wrap:wrap;gap:16px;margin:0 0 14px 0;align-items:flex-end}
 .filters label{font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:6px;min-width:170px}
 select,input{
   padding:7px 9px;font-size:14px;border:1px solid #bbc2c9;border-radius:6px;
   font-family:inherit;background:#fff;
 }
 ul{list-style:none;padding-left:0;margin:0}
 li{
   padding:11px 0 12px;border-bottom:1px solid #e3e7eb;line-height:1.38;
   font-size:14.5px;
 }
 li:last-child{border-bottom:none}
 a{word-break:break-all;text-decoration:none;color:#0645ad}
 a:hover{text-decoration:underline}
 .status-chip{
   display:inline-block;font-size:11px;font-weight:600;
   padding:2px 8px;margin-left:8px;border-radius:12px;vertical-align:middle;
   background:#e0e0e0;color:#333;text-transform:uppercase;letter-spacing:.5px;
 }
 .ok .status-chip{background:#d5f7d5;color:var(--ok)}
 .warn .status-chip{background:#ffe3c4;color:var(--warn)}
 .meta{
   font-size:12.5px;color:#555;margin-top:4px;line-height:1.35;
 }
 #empty{display:none;color:#d00;font-weight:600;margin-top:6px}
 footer{margin-top:30px;font-size:13px;color:#888;text-align:center}
 .legend{margin-top:16px;font-size:12.5px;line-height:1.45;color:#555}
 .legend-row{display:flex;align-items:center;gap:6px;margin:3px 0}
 .legend-row span.badge{
   display:inline-block;font-size:11px;font-weight:600;padding:2px 7px;border-radius:12px;
 }
 .badge.ok{background:#d5f7d5;color:var(--ok)}
 .badge.warn{background:#ffe3c4;color:var(--warn)}
 @media(max-width:720px){
   header h1{font-size:20px}
   .filters label{min-width:140px}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <img src="crawler.png" alt="Logo">
      <h1>Filtered-Category Link Checker</h1>
    </header>

    <label for="urls" class="top-lbl">Enter page-v2 URLs (one per line):</label>
    <textarea id="urls" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/12
https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/235"></textarea>

    <div class="actions">
      <button onclick="run()">Fetch JSON & Build Links</button>
      <button class="secondary" onclick="clearSession()">Clear Session</button>
      <button class="success" onclick="copyVisible()">Copy Visible Links</button>
    </div>
    <div id="loading">Processing… please wait</div>

    <div class="output">
      <div class="filters">
        <label>Status Filter
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">Show All</option>
            <option value="OK">OK</option>
            <option value="Warning">Warning</option>
          </select>
        </label>
        <label>Parent URL Filter
          <input id="parentFilter" placeholder="part of parent URL" oninput="applyFilters()">
        </label>
        <label>Title Filter
          <input id="titleFilter" placeholder="part of title" oninput="applyFilters()">
        </label>
      </div>

      <ul id="list"></ul>
      <p id="empty">No links match current filters.</p>

      <div class="legend">
        <div class="legend-row"><span class="badge ok">OK</span> Query returned hits (has products).</div>
        <div class="legend-row"><span class="badge warn">Warning</span> Query returned zero hits.</div>
      </div>
    </div>
  </div>

  <footer>© 2024 Filtered-Category Link Checker</footer>

<script>
/* ───── Cookie Helpers (kept for persistence) ───── */
function setCookie(n,v,d){const e=new Date(Date.now()+d*864e5).toUTCString();document.cookie=`${n}=${encodeURIComponent(v)}; expires=${e}; path=/`;}
function getCookie(n){return document.cookie.split('; ').reduce((r,v)=>{const p=v.split('=');return p[0]===n?decodeURIComponent(p[1]):r},'');}
function deleteCookie(n){setCookie(n,'',-1);}

/* ───── State ───── */
let visited = new Set();
let rows    = [];             // {link,status,parent,title}
const COOKIE='fcRows';

/* ───── Entry ───── */
async function run(){
  clearRuntime();        // fresh run (keeps textarea content)
  toggleLoad(true);

  const seeds=document.getElementById('urls').value
    .trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  if(!seeds.length){
    alert('Please enter at least one valid URL.');
    toggleLoad(false);
    return;
  }

  for(const parent of seeds){
    if(visited.has(parent)) continue;
    visited.add(parent);
    await processParent(parent);
  }

  await validate(rows.map(r=>r.link));
  saveRows();
  toggleLoad(false);
  render();
  applyFilters();
}

/* ───── Process a parent URL ───── */
async function processParent(parent){
  try{
    const res=await fetch(parent);
    if(!res.ok) throw new Error('Network response was not ok');
    const data=await res.json();

    const title=data?.data?.cms_items?.title || 'N/A';
    const {baseUrl1,baseUrl2}=splitBase(parent);
    const items=parseFilteredItems(data);
    const links=buildUrls(items,baseUrl1,baseUrl2);

    links.forEach(l=>{
      if(visited.has(l)) return;
      visited.add(l);
      rows.push({link:l,status:'',parent,title});
    });

  }catch(err){
    console.error('Parent fetch error:',err);
  }
}

/* ───── Helpers ───── */
function splitBase(u){
  const baseUrl1=u.split('/api')[0]+'/api/';
  const store=u.split('/')[5].replace('_','-');
  return {baseUrl1,baseUrl2:store};
}

/* SMART PARSER (classic + new_collection) */
function parseFilteredItems(json){
  const list=json?.data?.cms_items?.items||[];
  const out=[];
  list.forEach(({item})=>{
    /* classic pattern: segment contains ,filtered,catId,(k,v,k,v...) */
    item.split('||').forEach(str=>{
      const p=str.split(',');
      if(p[1]==='filtered'){
        const [, ,catId,...rest]=p;
        const filters={};
        for(let i=0;i<rest.length;i+=2){
          const k=rest[i],v=rest[i+1];
            if(!k||v===undefined) continue;
            (filters[k]=filters[k]||[]).push(v);
        }
        out.push({catId,filters});
      }
    });

    /* new_collection style: separate token 'filtered' followed by catId + filters CSV */
    const seg=item.split('||');
    seg.forEach((s,idx)=>{
      if(s==='filtered' && seg[idx+1]){
        const parts=seg[idx+1].split(',');
        const catId=parts[0];
        const rest=parts.slice(1);
        const filters={};
        for(let i=0;i<rest.length;i+=2){
          const k=rest[i],v=rest[i+1];
          if(!k||v===undefined) continue;
          (filters[k]=filters[k]||[]).push(v);
        }
        out.push({catId,filters});
      }
    });
  });
  return out;
}

function encodeSlashes(v){return v.replace(/%2F/gi,'*slash*');}

/* Build URLs – ONLY replace hyphen with comma for price or jb_discount_percentage values */
function buildUrls(entries,b1,b2){
  return entries.map(({catId,filters})=>{
    let url=`${b1}catalogv2/product/store/${b2}/category_ids/${catId}`;
    for(const [k,vals] of Object.entries(filters)){
      if(k==='sort'){
        const [field,dir]=(decodeURIComponent(vals[0]).replace('%3A',':')).split(':');
        url+=`/sort-${field}/${dir}`;
        continue;
      }
      let processed = vals.slice();
      if(k==='price' || k==='jb_discount_percentage'){
        processed = processed.map(v=>v.replace(/-/g,',')); // special keys
      }
      const joined=encodeSlashes(processed.join(','));
      url+=`/${k}/${joined}`;
    }
    url+='/aggregation/true/size/12';
    if(!('sort' in filters)) url+='/sort-priority/asc';
    return url;
  });
}

async function validate(links){
  for(const l of links){
    try{
      const r=await fetch(l);
      const j=await r.json();
      const ok=(j?.hits?.hits || j?.data?.hits?.hits || []).length>0;
      setStatus(l,ok?'OK':'Warning');
    }catch{
      setStatus(l,'Warning');
    }
  }
}
function setStatus(link,s){rows.forEach(r=>{if(r.link===link)r.status=s});}

/* ───── Persistence ───── */
function saveRows(){setCookie(COOKIE,JSON.stringify(rows),7);}
function loadRows(){
  const c=getCookie(COOKIE);
  if(!c) return;
  try{
    JSON.parse(c).forEach(r=>{
      rows.push(r);
      visited.add(r.link); visited.add(r.parent);
    });
  }catch{}
}

/* ───── UI / Runtime Control ───── */
function toggleLoad(on){document.getElementById('loading').style.display=on?'block':'none';}

function render(){
  const ul=document.getElementById('list');
  ul.innerHTML='';
  rows.forEach(r=>{
    const li=document.createElement('li');
    li.dataset.status=r.status;
    li.dataset.parent=r.parent.toLowerCase();
    li.dataset.title=r.title.toLowerCase();
    li.className = r.status==='OK'?'ok':'warn';
    li.innerHTML=`
      <a href="${r.link}" target="_blank">${r.link}</a>
      <span class="status-chip">${r.status}</span>
      <div class="meta">
        Parent: ${r.parent}<br>
        Title: ${r.title}
      </div>
    `;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = rows.length ? 'none':'block';
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const parentTerm=document.getElementById('parentFilter').value.toLowerCase();
  const titleTerm=document.getElementById('titleFilter').value.toLowerCase();
  let shown=0;
  document.querySelectorAll('#list li').forEach(li=>{
    const st = li.dataset.status;
    const p  = li.dataset.parent;
    const t  = li.dataset.title || '';
    const show = (want==='all'||st===want) &&
                 (!parentTerm || p.includes(parentTerm)) &&
                 (!titleTerm || t.includes(titleTerm));
    li.style.display=show?'':'none';
    if(show) shown++;
  });
  document.getElementById('empty').style.display = shown ? 'none':'block';
}

function copyVisible(){
  const links=[...document.querySelectorAll('#list li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>li.querySelector('a').href);
  if(!links.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links.join('\n')).then(()=>alert('Copied!'));
}

function clearSession(){
  deleteCookie(COOKIE);
  clearRuntime();
  document.getElementById('urls').value='';
  toggleLoad(false);
}

function clearRuntime(){
  rows=[];
  visited=new Set();
  document.getElementById('list').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  document.getElementById('titleFilter').value='';
}

/* Restore previous session if any */
loadRows();
render();
applyFilters();
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='category_filter'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

CATEGORY_FILTER_HTML,
    'cms' => <<<'CMS_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CMS Block Link Checker</title>
<style>
 :root{
   --blue:#007BFF;
   --bg:#f4f7fa;
   --card:#ffffff;
   --radius:10px;
   --shadow:0 4px 12px rgba(0,0,0,.1);
   --ok:#2e7d32;
   --warn:#d84315;
   --err:#b00020;
   --badge:#eceff1;
   --mono:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);
   min-height:100vh;
   display:flex;
   align-items:center;
   justify-content:center;
   padding:18px;
   color:#222;
 }
 .container{
   width:100%;max-width:980px;
   background:var(--card);
   border-radius:var(--radius);
   box-shadow:var(--shadow);
   padding:24px 26px 30px;
   display:flex;
   flex-direction:column;
   gap:16px;
 }
 header{
   display:flex;
   align-items:center;
   justify-content:space-between;
   padding-bottom:12px;
   border-bottom:2px solid var(--blue);
 }
 header h1{font-size:24px;color:var(--blue);margin:0}
 header img{width:54px;height:54px}

 textarea{
   width:100%;height:150px;
   padding:12px 14px;
   border:1px solid #cbd3dc;
   border-radius:8px;
   font-size:15px;
   resize:vertical;
 }

 .controls{display:flex;flex-wrap:wrap;gap:10px}
 button{
   background:var(--blue);
   border:none;
   color:#fff;
   padding:10px 18px;
   font-size:15px;
   border-radius:7px;
   cursor:pointer;
   line-height:1.1;
   display:inline-flex;
   align-items:center;
   gap:6px;
   transition:.25s;
 }
 button:hover{background:#005ed0}
 button.secondary{background:#546e7a}
 button.secondary:hover{background:#455a64}
 button.copy{background:#00906d}
 button.copy:hover{background:#007354}

 #loading{display:none;font-weight:600;color:#f90}

 .panel{
   margin-top:4px;
   padding:20px 20px 26px;
   background:#f9fafb;
   border:1px solid #dfe5eb;
   border-radius:10px;
 }
 .panel h3{margin:0 0 14px;font-size:20px;color:#333}

 .filters{
   display:flex;
   flex-wrap:wrap;
   gap:14px;
   margin-bottom:10px;
   align-items:flex-end;
 }
 .filters label{
   font-size:14px;
   font-weight:600;
   color:#333;
   display:flex;
   flex-direction:column;
   gap:6px;
 }
 select,input[type=text]{
   min-width:210px;
   padding:8px 10px;
   border:1px solid #bfc9d3;
   border-radius:6px;
   font-size:14px;
   background:#fff;
 }
 input::placeholder{color:#999}

 ul#allLinks{list-style:none;margin:0;padding:0}
 ul#allLinks li{
   padding:10px 10px 12px;
   border-bottom:1px solid #e3e8ee;
   display:flex;
   flex-direction:column;
   gap:4px;
   font-size:14px;
 }
 ul#allLinks li:last-child{border-bottom:none}

 a{color:#0d47a1;text-decoration:none;word-break:break-all}
 a:hover{text-decoration:underline}

 .status-badge{
   display:inline-block;
   font-size:11px;
   letter-spacing:.5px;
   font-weight:700;
   padding:3px 8px 4px;
   border-radius:100px;
   background:var(--badge);
   color:#37474f;
   vertical-align:middle;
   font-family:var(--mono);
 }
 .ok    .status-badge{background:var(--ok);color:#fff}
 .warn  .status-badge{background:var(--warn);color:#fff}
 .err   .status-badge{background:var(--err);color:#fff}

 .meta-line{font-size:12px;color:#555}
 .empty-msg{color:#d32f2f;font-weight:600;margin:6px 4px 2px}

 .legend{
   margin-top:18px;
   font-size:12.5px;
   line-height:1.5;
   color:#455a64;
   display:flex;
   flex-direction:column;
   gap:4px;
 }
 .legend .row{
   display:flex;
   align-items:center;
   gap:8px;
 }

 footer{margin-top:22px;text-align:center;font-size:13px;color:#888}

 @media(max-width:620px){
   .filters label{width:100%}
   select,input[type=text]{min-width:unset;width:100%}
   button{flex:1 1 auto}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>CMS Block Link Checker</h1>
  </header>

  <label for="urlInput" style="font-weight:600;font-size:14px;">Enter page-v2 URLs (one per line):</label>
  <textarea id="urlInput" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/123"></textarea>

  <div class="controls">
    <button onclick="startCrawling()">Fetch JSON & Crawl CMS Blocks</button>
    <button class="secondary" onclick="clearSession()">Clear Session</button>
    <button class="copy" onclick="copyVisible()">Copy Visible Links</button>
  </div>

  <div id="loading">Processing… please wait</div>

  <div class="panel">
    <h3>Generated CMS Page Links</h3>
    <div class="filters">
      <label>
        Status Filter
        <select id="statusFilter" onchange="applyFilters()">
          <option value="all">Show All</option>
          <option value="OK">OK</option>
          <option value="Warning">Warning</option>
        </select>
      </label>
      <label>
        Parent URL Filter
        <input id="parentFilter" type="text" placeholder="part of source URL" oninput="applyFilters()">
      </label>
    </div>

    <ul id="allLinks"></ul>
    <p id="empty" class="empty-msg" style="display:none;">No CMS page links found for current filters.</p>

    <div class="legend">
      <div class="row"><span class="status-badge ok">OK</span> <strong>OK</strong> – JSON has <code>data === null</code> OR contains CMS items.</div>
      <div class="row"><span class="status-badge warn">WARN</span> <strong>Warning</strong> – JSON fetched but no CMS items (empty).</div>
      <div class="row"><span class="status-badge err">ERR</span> <strong>Error</strong> – Fetch / parse problem (only shown under “Show All”).</div>
    </div>
  </div>

  <footer>© 2024 CMS Block Link Checker</footer>
</div>

<script>
/* ─────────────── State & Persistence ─────────────── */
const processedParents=new Set();   // parents already crawled
const queuedChildren =new Set();    // child cms page URLs generated
const rows=[];                      // {link,parent,status}
const COOKIE_KEY='cmsAllLinks';

function setCookie(n,v,d){
  const e=new Date(Date.now()+d*864e5).toUTCString();
  document.cookie=n+'='+encodeURIComponent(v)+'; expires='+e+'; path=/';
}
function getCookie(n){
  return document.cookie.split('; ').reduce((r,v)=>{
    const p=v.split('=');
    return p[0]===n?decodeURIComponent(p[1]):r;
  },'');
}
function deleteCookie(n){setCookie(n,'',-1);}

function saveRows(){
  setCookie(COOKIE_KEY,JSON.stringify(rows),7);
}
function loadRows(){
  const c=getCookie(COOKIE_KEY);
  if(!c) return;
  try{
    JSON.parse(c).forEach(r=>{
      rows.push(r);
      processedParents.add(r.parent);
      queuedChildren.add(r.link);
    });
  }catch{}
}

/* ─────────────── Crawl Logic ─────────────── */
function startCrawling(){
  const seeds=document.getElementById('urlInput').value.trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  if(!seeds.length){alert('Please enter at least one valid URL.');return;}
  crawl(seeds);
}

async function crawl(initialQueue){
  setLoading(true);
  let queue=[...initialQueue];

  while(queue.length){
    const batch=queue; queue=[];
    for(const parent of batch){
      if(processedParents.has(parent)) continue;
      processedParents.add(parent);

      try{
        const resp=await fetch(parent);
        if(!resp.ok) throw new Error('Network response was not ok.');
        const json=await resp.json();

        const blocks=extractCmsBlocks(json);
        const base=parent.substring(0,parent.lastIndexOf('/')+1);
        const childLinks=blocks.map(code=>base+code);

        childLinks.forEach(l=>{
          if(queuedChildren.has(l)) return;
            queuedChildren.add(l);
            rows.push({link:l,parent,status:'PENDING'});
            queue.push(l);  // recursive crawl
        });

        // mark parent itself (if it is also a CMS endpoint) result
        // Only add if it wasn't already considered
        if(!queuedChildren.has(parent)){
          queuedChildren.add(parent);
          rows.push({link:parent,parent,status:'PENDING'});
        }
      }catch(e){
        rows.push({link:parent,parent,status:'Error'});
        console.error(e);
      }
    }
    // persist incremental progress
    saveRows();
  }

  await validate();
  saveRows();
  setLoading(false);
  render();
  applyFilters();
}

/* Extract cms codes from cms_items */
function extractCmsBlocks(data){
  const items=data?.data?.cms_items?.items||[];
  const names=[];
  items.forEach(({item})=>{
    item.split('||').forEach(entry=>{
      const parts=entry.split(',');
      if(parts[1]==='cms' && parts[2]) names.push(parts[2]);
    });
  });
  return names;
}

async function validate(){
  for(const row of rows){
    if(row.status!=='PENDING') continue;
    try{
      const r=await fetch(row.link);
      if(!r.ok){row.status='Error';continue;}
      const j=await r.json();
      const ok = (j?.data===null) || dataHasCmsItems(j);
      row.status=ok?'OK':'Warning';
    }catch{
      row.status='Error';
    }
  }
}

function dataHasCmsItems(d){
  return d?.data?.cms_items?.items?.length>0;
}

/* ─────────────── UI ─────────────── */
function setLoading(on){document.getElementById('loading').style.display=on?'block':'none';}

function render(){
  const ul=document.getElementById('allLinks');
  ul.innerHTML='';
  rows.forEach(({link,parent,status})=>{
    const li=document.createElement('li');
    li.dataset.status=status;
    li.dataset.parent=parent.toLowerCase();
    let cls='';
    if(status==='OK') cls='ok';
    else if(status==='Warning') cls='warn';
    else if(status==='Error') cls='err';

    if(link.startsWith('http')){
      li.innerHTML=`
        <div>
          <a href="${link}" target="_blank">${link}</a>
          <span class="status-badge ${cls}">${status==='Warning'?'WARN':status.toUpperCase()}</span>
        </div>
        <div class="meta-line">Parent: ${parent}</div>`;
    }else{
      li.innerHTML=`<div>${link} <span class="status-badge err">ERROR</span></div>`;
    }
    li.className=cls;
    ul.appendChild(li);
  });
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const term=document.getElementById('parentFilter').value.trim().toLowerCase();
  let visible=0;
  document.querySelectorAll('#allLinks li').forEach(li=>{
    const st=li.dataset.status;
    const parent=li.dataset.parent||'';
    let show=false;
    if(want==='all') show=true;
    else if(want==='OK' && st==='OK') show=true;
    else if(want==='Warning' && st==='Warning') show=true;
    if(show && term && !parent.includes(term)) show=false;
    li.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display=visible?'none':'block';
}

function copyVisible(){
  const txt=[...document.querySelectorAll('#allLinks li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>{const a=li.querySelector('a');return a?a.href:'';})
    .filter(Boolean)
    .join('\n');
  if(!txt){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(txt).then(()=>alert('Copied!'));
}

function clearSession(){
  deleteCookie(COOKIE_KEY);
  processedParents.clear();
  queuedChildren.clear();
  rows.length=0;
  document.getElementById('urlInput').value='';
  document.getElementById('allLinks').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  setLoading(false);
}

/* Initial load from cookie */
loadRows();
render();
applyFilters();
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='cms'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

CMS_HTML,
    'getcategories' => <<<'GETCATEGORIES_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Get Categories Links</title>
<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --radius:10px;--ok:#096b09;--warn:#8d4a00;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
   padding:18px 10px;
 }
 .container{
   width:100%;max-width:980px;background:var(--card);
   border-radius:var(--radius);padding:22px 24px 26px;
   box-shadow:var(--shadow);
 }
 header{display:flex;align-items:center;justify-content:space-between;
        border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:20px}
 header img{width:54px;height:54px}
 header h1{font-size:24px;color:var(--blue);margin:0;font-weight:600}
 label.top-lbl{font-weight:600;font-size:14px;display:block}
 textarea{
   width:100%;height:140px;margin:14px 0 16px;
   padding:10px 12px;border:1px solid #c7cdd4;border-radius:6px;font-size:15px;
   resize:vertical;line-height:1.35;
 }
 .actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:4px}
 button{
   background:var(--blue);color:#fff;border:none;
   padding:10px 16px;border-radius:6px;cursor:pointer;
   font-size:15px;font-weight:600;letter-spacing:.2px;
   transition:background .25s;
 }
 button:hover{background:#0069d6}
 button.secondary{background:#555}
 button.secondary:hover{background:#333}
 button.success{background:#00906d}
 button.success:hover{background:#007256}
 #loading{display:none;color:#ff9800;font-weight:700;margin:4px 0 10px}
 .output{
   margin-top:4px;padding:18px 18px 16px;background:#f9f9f9;
   border:1px solid #d9dfe5;border-radius:10px;
 }
 .filters{display:flex;flex-wrap:wrap;gap:16px;margin:0 0 14px 0;align-items:flex-end}
 .filters label{font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:6px;min-width:170px}
 select,input{
   padding:7px 9px;font-size:14px;border:1px solid #bbc2c9;border-radius:6px;
   font-family:inherit;background:#fff;
 }
 ul{list-style:none;padding-left:0;margin:0}
 li{
   padding:11px 0 12px;border-bottom:1px solid #e3e7eb;line-height:1.38;
   font-size:14.5px;
 }
 li:last-child{border-bottom:none}
 a{word-break:break-all;text-decoration:none;color:#0645ad}
 a:hover{text-decoration:underline}
 .status-chip{
   display:inline-block;font-size:11px;font-weight:600;
   padding:2px 8px;margin-left:8px;border-radius:12px;vertical-align:middle;
   background:#e0e0e0;color:#333;text-transform:uppercase;letter-spacing:.5px;
 }
 .ok .status-chip{background:#d5f7d5;color:var(--ok)}
 .warn .status-chip{background:#ffe3c4;color:var(--warn)}
 .meta{
   font-size:12.5px;color:#555;margin-top:4px;line-height:1.35;
 }
 #empty{display:none;color:#d00;font-weight:600;margin-top:6px}
 footer{margin-top:30px;font-size:13px;color:#888;text-align:center}
 .legend{margin-top:16px;font-size:12.5px;line-height:1.45;color:#555}
 .legend-row{display:flex;align-items:center;gap:6px;margin:3px 0}
 .legend-row span.badge{
   display:inline-block;font-size:11px;font-weight:600;padding:2px 7px;border-radius:12px;
 }
 .badge.ok{background:#d5f7d5;color:var(--ok)}
 .badge.warn{background:#ffe3c4;color:var(--warn)}
 @media(max-width:720px){
   header h1{font-size:20px}
   .filters label{min-width:140px}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <img src="crawler.png" alt="Logo">
      <h1>Get Categories Links</h1>
    </header>

    <label for="urls" class="top-lbl">Enter /getAllCategories URLs (one per line):</label>
    <textarea id="urls" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/getAllCategories"></textarea>

    <div class="actions">
      <button onclick="run()">Fetch & Generate Links</button>
      <button class="secondary" onclick="clearSession()">Clear Session</button>
      <button class="success" onclick="copyVisible()">Copy Visible Links</button>
    </div>
    <div id="loading">Processing… please wait</div>

    <div class="output">
      <div class="filters">
        <label>Status Filter
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">Show All</option>
            <option value="OK">OK</option>
            <option value="Warning">Warning</option>
          </select>
        </label>
        <label>Parent URL Filter
          <input id="parentFilter" placeholder="part of parent URL" oninput="applyFilters()">
        </label>
      </div>

      <ul id="list"></ul>
      <p id="empty">No links match current filters.</p>

      <div class="legend">
        <div class="legend-row"><span class="badge ok">OK</span> Category page returns CMS items (has content).</div>
        <div class="legend-row"><span class="badge warn">Warning</span> Category page returns no CMS items.</div>
      </div>
    </div>
  </div>

  <footer>© 2024 Get Categories Links</footer>

<script>
/* ───── Cookie Helpers ───── */
function setCookie(n,v,d){const e=new Date(Date.now()+d*864e5).toUTCString();document.cookie=`${n}=${encodeURIComponent(v)}; expires=${e}; path=/`;}
function getCookie(n){return document.cookie.split('; ').reduce((r,v)=>{const p=v.split('=');return p[0]===n?decodeURIComponent(p[1]):r},'');}
function deleteCookie(n){setCookie(n,'',-1);}

/* ───── State ───── */
let visited=new Set();          // fetched parents + generated category URLs
let rows=[];                    // {link,status,parent}
const COOKIE_KEY='gcLinks';

/* ───── Main Run ───── */
async function run(){
  clearRuntime();          // Reset previous in-page results (keep textarea content)
  toggleLoading(true);

  const seeds=document.getElementById('urls').value
    .trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  if(!seeds.length){
    alert('Please enter at least one valid URL.');
    toggleLoading(false);
    return;
  }

  for(const parent of seeds){
    if(visited.has(parent)) continue;
    visited.add(parent);
    await processParent(parent);
  }

  await validate(rows.map(r=>r.link));
  saveRows();
  toggleLoading(false);
  render();
  applyFilters();
}

/* ───── Process Parent ───── */
async function processParent(parent){
  try{
    const res=await fetch(parent);
    if(!res.ok) throw new Error('Network response was not ok');
    const data=await res.json();

    const codes=extractCodes(data);
    const {basePrefix,store}=splitBase(parent);
    const links=codes.map(c=>`${basePrefix}${store}/cmspage/page-v2/${c}`);

    links.forEach(l=>{
      if(visited.has(l)) return;
      visited.add(l);
      rows.push({link:l,status:'',parent});
    });

  }catch(err){
    console.error('Parent fetch error:',err);
  }
}

/* ───── Helpers ───── */
function splitBase(url){
  const parts=url.split('/');
  const basePrefix=parts.slice(0,5).join('/')+'/'; // …/api/v2/
  return {basePrefix,store:parts[5]};
}
function extractCodes(json){
  const arr=json?.data||[];
  return arr.map(o=>o.category_code).filter(Boolean);
}
async function validate(links){
  for(const link of links){
    try{
      const r=await fetch(link);
      const js=await r.json();
      const ok=hasCms(js);
      setStatus(link,ok?'OK':'Warning');
    }catch{
      setStatus(link,'Warning');
    }
  }
}
function hasCms(d){
  const items=d?.data?.cms_items?.items??[];
  return Array.isArray(items) && items.length>0;
}
function setStatus(link,st){rows.forEach(r=>{if(r.link===link) r.status=st;});}

/* ───── Persistence ───── */
function saveRows(){setCookie(COOKIE_KEY,JSON.stringify(rows),7);}
function loadRows(){
  const c=getCookie(COOKIE_KEY);
  if(!c) return;
  try{
    JSON.parse(c).forEach(r=>{
      rows.push(r);
      visited.add(r.link);
      visited.add(r.parent);
    });
  }catch{}
}

/* ───── UI / Runtime Control ───── */
function toggleLoading(on){document.getElementById('loading').style.display=on?'block':'none';}

function render(){
  const ul=document.getElementById('list');
  ul.innerHTML='';
  rows.forEach(r=>{
    if(!r.parent) return;
    const li=document.createElement('li');
    li.dataset.status=r.status;
    li.dataset.parent=r.parent.toLowerCase();
    li.className = r.status==='OK'?'ok':'warn';
    li.innerHTML = `
      <a href="${r.link}" target="_blank">${r.link}</a>
      <span class="status-chip">${r.status}</span>
      <div class="meta">
        Parent: ${r.parent}
      </div>
    `;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = rows.length ? 'none':'block';
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const pterm=document.getElementById('parentFilter').value.toLowerCase();
  let shown=0;
  document.querySelectorAll('#list li').forEach(li=>{
    const st=li.dataset.status;
    const p =li.dataset.parent;
    const show=(want==='all'||st===want) && (!pterm||p.includes(pterm));
    li.style.display=show?'':'none';
    if(show) shown++;
  });
  document.getElementById('empty').style.display = shown ? 'none':'block';
}

function copyVisible(){
  const links=[...document.querySelectorAll('#list li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>li.querySelector('a').href);
  if(!links.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links.join('\n')).then(()=>alert('Copied!'));
}

function clearSession(){
  deleteCookie(COOKIE_KEY);
  clearRuntime();
  document.getElementById('urls').value='';
  toggleLoading(false);
}

function clearRuntime(){
  rows=[];
  visited=new Set();
  document.getElementById('list').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
}

/* Backwards compatible alias (original button label was Clear Cache) */
function clearCache(){ clearSession(); }

/* Restore previous session if any */
loadRows();
render();
applyFilters();
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='getcategories'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

GETCATEGORIES_HTML,
    'images' => <<<'IMAGES_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Image Link Checker</title>
<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --radius:10px;--ok:#096b09;--warn:#8d4a00;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
   padding:18px 10px;
 }
 .container{
   width:100%;max-width:980px;background:var(--card);
   border-radius:var(--radius);padding:22px 24px 26px;
   box-shadow:var(--shadow);
 }
 header{display:flex;align-items:center;justify-content:space-between;
        border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:20px}
 header img{width:54px;height:54px}
 header h1{font-size:24px;color:var(--blue);margin:0;font-weight:600}
 label.top-lbl{font-weight:600;font-size:14px;display:block}
 textarea{
   width:100%;height:140px;margin:14px 0 16px;
   padding:10px 12px;border:1px solid #c7cdd4;border-radius:6px;font-size:15px;
   resize:vertical;line-height:1.35;
 }
 .actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:4px}
 button{
   background:var(--blue);color:#fff;border:none;
   padding:10px 16px;border-radius:6px;cursor:pointer;
   font-size:15px;font-weight:600;letter-spacing:.2px;
   transition:background .25s;
 }
 button:hover{background:#0069d6}
 button.secondary{background:#555}
 button.secondary:hover{background:#333}
 button.success{background:#00906d}
 button.success:hover{background:#007256}
 #loading{display:none;color:#ff9800;font-weight:700;margin:4px 0 10px}
 .output{
   margin-top:4px;padding:18px 18px 16px;background:#f9f9f9;
   border:1px solid #d9dfe5;border-radius:10px;
 }
 .filters{display:flex;flex-wrap:wrap;gap:16px;margin:0 0 14px 0;align-items:flex-end}
 .filters label{font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:6px;min-width:170px}
 select,input{
   padding:7px 9px;font-size:14px;border:1px solid #bbc2c9;border-radius:6px;
   font-family:inherit;background:#fff;
 }
 ul{list-style:none;padding-left:0;margin:0}
 li{
   padding:11px 0 12px;border-bottom:1px solid #e3e7eb;line-height:1.38;
   font-size:14.5px;
 }
 li:last-child{border-bottom:none}
 a{word-break:break-all;text-decoration:none;color:#0645ad}
 a:hover{text-decoration:underline}
 .status-chip{
   display:inline-block;font-size:11px;font-weight:600;
   padding:2px 8px;margin-left:8px;border-radius:12px;vertical-align:middle;
   background:#e0e0e0;color:#333;text-transform:uppercase;letter-spacing:.5px;
 }
 .valid .status-chip{background:#d5f7d5;color:var(--ok)}
 .invalid .status-chip{background:#ffe3c4;color:var(--warn)}
 .meta{
   font-size:12.5px;color:#555;margin-top:4px;line-height:1.35;
 }
 #empty{display:none;color:#d00;font-weight:600;margin-top:6px}
 footer{margin-top:30px;font-size:13px;color:#888;text-align:center}
 .legend{margin-top:16px;font-size:12.5px;line-height:1.45;color:#555}
 .legend-row{display:flex;align-items:center;gap:6px;margin:3px 0}
 .legend-row span.badge{
   display:inline-block;font-size:11px;font-weight:600;padding:2px 7px;border-radius:12px;
 }
 .badge.valid{background:#d5f7d5;color:var(--ok)}
 .badge.invalid{background:#ffe3c4;color:var(--warn)}
 @media(max-width:720px){
   header h1{font-size:20px}
   .filters label{min-width:140px}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <img src="crawler.png" alt="Logo">
      <h1>Image Link Checker</h1>
    </header>

    <label for="urls" class="top-lbl">Enter page-v2 URLs (one per line):</label>
    <textarea id="urls" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/12
https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/235"></textarea>

    <div class="actions">
      <button onclick="run()">Fetch JSON & Check Images</button>
      <button class="secondary" onclick="clearSession()">Clear Session</button>
      <button class="success" onclick="copyVisible()">Copy Visible Links</button>
    </div>
    <div id="loading">Processing… please wait</div>

    <div class="output">
      <div class="filters">
        <label>Status Filter
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">Show All</option>
            <option value="Valid">Valid</option>
            <option value="Invalid">Invalid</option>
          </select>
        </label>
        <label>Parent URL Filter
          <input id="parentFilter" placeholder="part of parent URL" oninput="applyFilters()">
        </label>
      </div>

      <ul id="list"></ul>
      <p id="empty">No image links match current filters.</p>

      <div class="legend">
        <div class="legend-row"><span class="badge valid">Valid</span> Image request succeeded (no error marker detected).</div>
        <div class="legend-row"><span class="badge invalid">Invalid</span> Image returned error (or fetch failed).</div>
      </div>
    </div>
  </div>

  <footer>© 2024 Image Link Checker</footer>

<script>
/* ───── State ───── */
let rows=[];              // {link,parent,status}
let visitedParents=new Set();
const visitedImages=new Set(); // prevent duplicate link rows within a single run

/* ───── Main Run ───── */
async function run(){
  clearRuntime();               // fresh run (keeps textarea content)
  toggleLoading(true);

  const seeds=document.getElementById('urls').value
    .trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  if(!seeds.length){
    alert('Please enter at least one valid URL.');
    toggleLoading(false);
    return;
  }

  for(const parent of seeds){
    if(visitedParents.has(parent)) continue;
    visitedParents.add(parent);
    await processParent(parent);
  }

  toggleLoading(false);
  render();
  applyFilters();
}

/* ───── Parent Processing ───── */
async function processParent(parent){
  try{
    const res=await fetch(parent);
    if(!res.ok) throw new Error('Network error');
    const data=await res.json();
    const imgs=extractImageLinks(data);
    await checkImageLinks(imgs,parent);
  }catch(e){
    console.error('Fetch parent failed:',parent,e);
  }
}

/* ───── Extract Images (retain original logic / behavior) ───── */
function extractImageLinks(data){
  const items=data?.data?.cms_items?.items||[];
  const out=[];
  items.forEach(({item})=>{
    item.split('||').forEach(entry=>{
      const first=entry.split(',')[0];
      if(/\.(jpg|jpeg|png|gif|webp)$/i.test(first)) out.push(first);
    });
  });
  return out;
}

/* ───── Check Images (kept functional behavior) ─────
   NOTE: original logic considered an image valid if *either* error tag absent.
   We preserve that OR logic to avoid changing functionality. */
async function checkImageLinks(links,parent){
  for(const link of links){
    if(visitedImages.has(link+parent)) continue;
    visitedImages.add(link+parent);
    try{
      const r=await fetch(link,{method:'GET'});
      const txt=await r.text();
      // original: const valid=!body.includes('<Error>')||!body.includes('</Error>');
      const valid = (!txt.includes('<Error>')) || (!txt.includes('</Error>'));
      rows.push({link,parent,status:valid?'Valid':'Invalid'});
    }catch{
      rows.push({link,parent,status:'Invalid'});
    }
  }
}

/* ───── Rendering & Filters ───── */
function render(){
  const ul=document.getElementById('list');
  ul.innerHTML='';
  rows.forEach(r=>{
    const li=document.createElement('li');
    li.dataset.status=r.status;
    li.dataset.parent=r.parent.toLowerCase();
    li.className=r.status==='Valid'?'valid':'invalid';
    li.innerHTML=`
      <a href="${r.link}" target="_blank">${r.link}</a>
      <span class="status-chip">${r.status}</span>
      <div class="meta">
        <span>Parent: ${r.parent}</span>
      </div>`;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = rows.length ? 'none':'block';
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const parentTerm=document.getElementById('parentFilter').value.toLowerCase();
  let visible=0;
  document.querySelectorAll('#list li').forEach(li=>{
    const st=li.dataset.status;
    const p =li.dataset.parent;
    const show = (want==='all'||st===want) && (!parentTerm || p.includes(parentTerm));
    li.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display = visible ? 'none':'block';
}

/* ───── Copy Visible ───── */
function copyVisible(){
  const links=[...document.querySelectorAll('#list li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>li.querySelector('a').href);
  if(!links.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links.join('\n')).then(()=>alert('Copied!'));
}

/* ───── Session / Runtime Reset ───── */
function clearRuntime(){
  rows=[];
  visitedParents.clear();
  visitedImages.clear();
  document.getElementById('list').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
}

function clearSession(){
  clearRuntime();
  document.getElementById('urls').value='';
  toggleLoading(false);
}

/* ───── Utility ───── */
function toggleLoading(on){
  document.getElementById('loading').style.display=on?'block':'none';
}
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='images'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

IMAGES_HTML,
    'login' => <<<'LOGIN_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Login Flow Checker (Multi-Country)</title>
<style>
 :root{--blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);--radius:10px}
 *{box-sizing:border-box;margin:0;padding:0}
 body{
   font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:center;
 }
 .container{width:90%;max-width:980px;background:var(--card);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow)}
 header{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:18px}
 header img{width:52px;height:52px}
 header h1{font-size:22px;color:var(--blue);margin:0}
 textarea{width:100%;height:140px;margin:14px 0;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:15px;font-family:inherit;resize:vertical}
 .row{display:flex;gap:10px;flex-wrap:wrap}
 input[type="text"],input[type="password"]{flex:1 1 260px;padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:15px}
 button{background:var(--blue);color:#fff;border:none;padding:10px 16px;border-radius:6px;cursor:pointer;font-size:15px;font-weight:600;transition:background .25s}
 button:hover{background:#005fbe}
 .muted{background:#666}.muted:hover{background:#555}
 .copy{background:#00906d}.copy:hover{background:#007a5c}
 #loading{display:none;color:#ff9800;font-weight:700;margin-top:6px}
 fieldset{border:1px solid #ddd;border-radius:8px;padding:12px;margin:10px 0}
 legend{padding:0 6px;color:#333;font-weight:700}
 .country-grid{display:flex;flex-wrap:wrap;gap:12px}
 .country-grid label{display:flex;align-items:center;gap:8px;border:1px solid #ddd;border-radius:6px;padding:6px 10px;background:#fff;cursor:pointer}
 .output{margin-top:16px;padding:16px;background:#f9f9f9;border:1px solid #ddd;border-radius:8px}
 ul{list-style:none;padding-left:0;margin:0}
 li{padding:10px 0;border-bottom:1px solid #e1e1e1;line-height:1.35}
 li:last-child{border-bottom:none}
 .meta{font-size:12.5px;color:#555;margin-top:2px;word-break:break-all}
 .chip{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;vertical-align:middle;letter-spacing:.4px;margin-left:6px}
 .success{background:#d6f6d6;color:#0a6e0a}
 .failure{background:#ffe1cc;color:#8d4500}
 .error{background:#ffd9d9;color:#a10000}
 footer{margin-top:18px;color:#888;font-size:13px;text-align:center}
 .small{font-size:12px;color:#666;margin-top:8px}
 code{background:#eef;padding:1px 5px;border-radius:4px}
 .inline{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>Login Flow Checker</h1>
  </header>

  <p style="margin-bottom:6px;font-size:14px">
    Paste one or more credential lines below (<strong>JSON object</strong>, <strong>JSON array</strong>, or <code>email,password</code>), or use the quick fields.
  </p>
  <textarea id="bulk" placeholder='Examples:
{"username":"0533645794","password":"Jarir13245!"}
[{"username":"u1@example.com","password":"P1!"},{"username":"u2@example.com","password":"P2!"}]
user@example.com,SuperSecret!'></textarea>

  <div class="row">
    <input id="u" type="text" placeholder="Quick username/email (optional)">
    <input id="p" type="password" placeholder="Quick password (optional)">
  </div>

  <fieldset>
    <legend>Countries</legend>
    <div class="inline" style="margin-bottom:8px">
      <button type="button" class="muted" onclick="selectAllCountries(true)">Select All</button>
      <button type="button" class="muted" onclick="selectAllCountries(false)">Clear</button>
    </div>
    <div class="country-grid">
      <label><input type="checkbox" class="country" value="SA" checked> Saudi Arabia (SA)</label>
      <label><input type="checkbox" class="country" value="AE" checked> United Arab Emirates (AE)</label>
      <label><input type="checkbox" class="country" value="KW" checked> Kuwait (KW)</label>
      <label><input type="checkbox" class="country" value="QA" checked> Qatar (QA)</label>
      <label><input type="checkbox" class="country" value="BH" checked> Bahrain (BH)</label>
    </div>
    <div class="small">We will call the store-specific endpoint: <code>/api/v2/&lt;store&gt;/user/login-v2</code> for each selected country.</div>
  </fieldset>

  <div class="row" style="margin-top:10px">
    <button onclick="run()">Try Login</button>
    <button class="muted" onclick="clearSession()">Clear Session</button>
    <button class="copy" onclick="copyVisible()">Copy Visible</button>
  </div>

  <div id="loading">Contacting login API…</div>

  <div class="output">
    <h3 style="margin-top:0">Results</h3>
    <ul id="list"></ul>
    <p id="empty" style="display:none;color:#d00;font-weight:600;margin-top:6px">No attempts yet.</p>
    <p class="small">
      <strong>SUCCESS rule:</strong> <code>success === true</code> AND <code>type === "COMMERCE_CUSTOMER_TOKEN_GENERATED"</code> AND a non-empty <code>data.token</code>.<br>
      Otherwise: <strong>FAILURE</strong>. Network/parse issues are <strong>ERROR</strong>.
    </p>
  </div>
</div>

<footer>© 2024 Login Flow Checker</footer>

<script>
/* Country → store mapping */
const COUNTRY_MAP = {
  SA: {store:'sa_en', label:'Saudi Arabia'},
  AE: {store:'ae_en', label:'United Arab Emirates'},
  KW: {store:'kw_en', label:'Kuwait'},
  QA: {store:'qa_en', label:'Qatar'},
  BH: {store:'bh_en', label:'Bahrain'}
};
const LOGIN_API = (store)=>`https://www.jarir.com/api/v2/${store}/user/login-v2`;

/* state */
const attempts = window.rows = []; // exposed for collector

/* helpers */
function mask(p){ if(!p) return ''; return p.length<=2 ? '*'.repeat(p.length) : p[0] + '*'.repeat(Math.max(1,p.length-2)) + p[p.length-1]; }

function selectAllCountries(on){
  document.querySelectorAll('.country').forEach(c=>c.checked=!!on);
}

/* Robust JSON parsing for the whole textarea first, then fallback per-line */
function parseBulk(){
  const raw = document.getElementById('bulk').value.trim();
  const rows = [];

  // 1) Try entire blob as JSON (object or array)
  if(raw){
    try{
      const all = JSON.parse(raw);
      if(Array.isArray(all)){
        all.forEach(obj=>{
          if(obj && obj.username && obj.password){
            rows.push({username:String(obj.username), password:String(obj.password)});
          }
        });
        if(rows.length) return addQuick(rows);
      }else if(all && typeof all==='object' && all.username && all.password){
        rows.push({username:String(all.username), password:String(all.password)});
        return addQuick(rows);
      }
    }catch{/* ignore, fallback */}
  }

  // 2) Fallback: line-by-line (JSON per line or CSV "email,password")
  if(raw){
    raw.split(/\r?\n/).forEach(line=>{
      const t=line.trim(); if(!t) return;
      if((t.startsWith('{') && t.endsWith('}')) || (t.startsWith('[') && t.endsWith(']'))){
        try{
          const v=JSON.parse(t);
          if(Array.isArray(v)){
            v.forEach(obj=>{
              if(obj && obj.username && obj.password){
                rows.push({username:String(obj.username), password:String(obj.password)});
              }
            });
          }else if(v && v.username && v.password){
            rows.push({username:String(v.username), password:String(v.password)});
          }
          return;
        }catch{/* fall through */}
      }
      const m=t.split(',');
      if(m.length>=2){
        rows.push({username:m[0].trim(), password:m.slice(1).join(',').trim()});
      }
    });
  }
  return addQuick(rows);
}

function addQuick(rows){
  const qU=document.getElementById('u').value.trim();
  const qP=document.getElementById('p').value;
  if(qU && qP) rows.push({username:qU,password:qP});
  return rows;
}

/* strict success decision */
function isSuccessResponse(j){
  const okSuccess = j?.success === true;
  const rightType = j?.type === 'COMMERCE_CUSTOMER_TOKEN_GENERATED';
  const hasToken  = typeof j?.data?.token === 'string' && j.data.token.trim().length>0;
  return okSuccess && rightType && hasToken;
}

/* main */
async function run(){
  const creds = parseBulk();
  const countries = [...document.querySelectorAll('.country:checked')].map(i=>i.value);
  if(!countries.length){ alert('Please select at least one country.'); return; }

  document.getElementById('loading').style.display='block';
  if(!creds.length){
    alert('Provide at least one username/password (JSON object/array or CSV line, or use the quick fields).');
    document.getElementById('loading').style.display='none';
    return;
  }

  for(const {username,password} of creds){
    for(const country of countries){
      const map = COUNTRY_MAP[country];
      if(!map) continue;

      try{
        const res = await fetch(LOGIN_API(map.store),{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ username, password })
        });
        const j = await res.json();
        const status = isSuccessResponse(j) ? 'SUCCESS' : 'FAILURE';
        const message = j?.message || j?.data?.message || '';

        attempts.push({
          username,
          masked: mask(password),
          country: country,
          store: map.store,
          status,
          message,
          url: username,    // for report
          parent: map.store // for report
        });
      }catch(e){
        attempts.push({
          username,
          masked: mask(password),
          country: country,
          store: map?.store || '?',
          status:'ERROR',
          message: String(e),
          url: username,
          parent: map?.store || '?'
        });
      }
      render(); // live update
    }
  }

  document.getElementById('loading').style.display='none';
  render();
}

function render(){
  const ul=document.getElementById('list');
  ul.innerHTML='';
  attempts.forEach(a=>{
    const cls = a.status==='SUCCESS' ? 'success' : (a.status==='FAILURE' ? 'failure' : 'error');
    const label = `${a.country} / ${a.store}`;
    const li=document.createElement('li');
    li.innerHTML = `
      <div><strong>${a.username}</strong> / <span style="font-family:monospace">${a.masked}</span>
        <span class="chip ${cls}">${a.status}</span>
      </div>
      <div class="meta">${label}${a.message?` — ${escapeHtml(a.message)}`:''}</div>
    `;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = attempts.length? 'none':'block';
}

function clearSession(){
  window.rows.length = 0;
  document.getElementById('bulk').value='';
  document.getElementById('u').value='';
  document.getElementById('p').value='';
  document.getElementById('list').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('loading').style.display='none';
  // Reset countries to ALL checked
  document.querySelectorAll('.country').forEach(el=>el.checked = true);
}

function copyVisible(){
  const out=[...document.querySelectorAll('#list li')].map(li=>li.innerText.trim()).join('\n\n');
  if(!out){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(out).then(()=>alert('Copied!'));
}

function escapeHtml(s){return (s||'').toString().replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='login'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

LOGIN_HTML,
    'price_checker' => <<<'PRICE_CHECKER_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SKU Detail Diff / Banner Price Checker</title>
<link rel="icon" href="favicon.ico">

<!-- Tesseract.js -->
<script src="https://unpkg.com/tesseract.js@5.0.4/dist/tesseract.min.js"></script>

<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --ok:#0b7a0b;--warn:#b56200;--err:#b00020;--muted:#666;--r:10px
 }
 *{box-sizing:border-box;margin:0;padding:0}
 body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:var(--bg);color:#222;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:18px}
 .container{width:100%;max-width:1280px;background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);padding:20px}
 header{display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:16px}
 header img{width:52px;height:52px}
 header h1{font-size:22px;color:var(--blue);margin:0}
 textarea{width:100%;height:130px;margin:10px 0;padding:10px 12px;border:1px solid #cfd6dd;border-radius:8px;font-size:15px;resize:vertical}
 .row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
 input,button{font-family:inherit;font-size:14px}
 input[type="text"],input[type="number"],input[type="password"]{padding:8px 10px;border:1px solid #cfd6dd;border-radius:8px;min-width:240px}
 button{background:var(--blue);color:#fff;border:none;border-radius:8px;padding:10px 14px;font-weight:600;cursor:pointer;transition:.25s}
 button:hover{background:#0566e5}
 .muted{background:#555}.copy{background:#00906d}
 .loading{display:none;color:#f90;font-weight:700;margin:6px 0}
 .panel{margin-top:14px;padding:14px;background:#f9fafb;border:1px solid #e4e9ee;border-radius:10px}
 .panel h3{margin:0 0 10px 0}
 .grid2{display:grid;grid-template-columns:1fr;gap:14px}
 .pair{display:grid;grid-template-columns:360px 1fr;gap:14px;align-items:start;border:1px solid #e5e9ef;border-radius:10px;background:#fff;overflow:hidden}
 .pair .left,.pair .right{padding:10px}
 .left{border-right:1px solid #eef2f7;background:#fff}
 .head{padding:8px 10px;border-bottom:1px solid #eef2f7;background:#fafbfd;font-weight:700;font-size:13px}
 .thumb{width:100%;height:auto;display:block;background:#eee;border-radius:8px}
 .badge{display:inline-block;font-size:11px;padding:2px 8px;border-radius:999px;font-weight:700;vertical-align:middle;margin-left:6px}
 .OK{background:#d9f6d9;color:var(--ok)} .NOMATCH{background:#ffe6cc;color:var(--warn)}
 .UNKNOWN{background:#eee;color:#444} .ERROR{background:#ffd9d9;color:#b00020}
 .meta{font-size:12px;color:#555;margin-top:6px}
 code{background:#eef;padding:1px 5px;border-radius:4px}
 .small{font-size:12px;color:#666}
 .pill{display:inline-block;background:#eef;border:1px solid #dde;padding:2px 6px;border-radius:999px;font-size:11px;margin:2px 4px 0 0}
 .col{display:flex;flex-direction:column;gap:8px}
 a{color:#0a58ca;text-decoration:none} a:hover{text-decoration:underline}
 .kv{display:grid;grid-template-columns:140px 1fr;gap:8px;font-size:12px}
 .kv div:nth-child(odd){color:#666}
 .ocr-src{font-size:12px;color:#333}
 .divider{height:1px;background:#eef2f7;margin:8px 0}
 .empty{display:none;margin-top:8px;color:#b00020;font-weight:600}
 label.toggle{display:flex;align-items:center;gap:6px}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>SKU Detail Diff / Banner Price Checker</h1>
  </header>

  <p class="small">Paste one or more <strong>CMS page-v2</strong> parent URLs (e.g. <code>.../cmspage/page-v2/home</code>). We extract (banner image + CMS slug) → OCR price → open child CMS → collect SKUs → fetch <code>final_price</code> via <code>catalogv1/product/store/&lt;store&gt;/sku/&lt;SKUS&gt;</code> → compare.</p>
  <textarea id="cmsInput" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/home"></textarea>

  <div class="row" style="margin-top:6px">
    <button onclick="run()">Scan & Compare</button>
    <button class="muted" onclick="clearSession()">Clear Session</button>
    <button class="copy" onclick="copyVisible()">Copy Visible</button>
    <label class="toggle"><input type="checkbox" id="imgEnhance" checked> Sharpen / binarize before OCR</label>
    <label class="toggle">Max SKUs per CMS: <input id="skuLimit" type="number" min="1" max="100" value="24" style="width:80px"></label>
  </div>

  <div class="panel">
    <h3>OCR Provider</h3>
    <div class="row">
      <label class="toggle"><input type="radio" name="ocrProvider" value="hf"> Hugging Face Space (DeepSeek-OCR)</label>
      <label class="toggle"><input type="radio" name="ocrProvider" value="tesseract" checked> Tesseract Pro (in-browser)</label>
      <input id="hfSpace" type="text" value="merterbak/DeepSeek-OCR-Demo" style="min-width:260px" title="owner/space-name">
      <input id="hfToken" type="password" placeholder="(optional) hf_… token" style="min-width:260px">
      <span class="small">Serve via http(s) to use HF; browsers block it from <code>file://</code> (CORS).</span>
    </div>
  </div>

  <div id="loading" class="loading">Processing… (OCR + API fetch)</div>

  <div class="panel">
    <h3>Discovered in Parent Page(s)</h3>
    <div id="discover" class="col"></div>
  </div>

  <div class="panel">
    <h3>Paired Results (Banner ↔ Comparison)</h3>
    <div id="pairs" class="grid2"></div>
    <p id="empty" class="empty">No banners / comparisons for the given input.</p>
  </div>

  <div class="panel">
    <h3>Manual Pairs (optional)</h3>
    <p class="small">One per line: <code>imageURL || cmsSlugOrSkuUrl</code>. A slug like <code>apple-iphone-17</code> is resolved using the first parent’s store; a full product API URL is used directly.</p>
    <textarea id="pairsInput" placeholder="https://wp-media/banner1.jpg || apple-iphone-17"></textarea>
    <div class="row" style="margin-top:6px">
      <button onclick="runPairs()">Scan Manual Pairs</button>
    </div>
  </div>

  <p class="small" style="margin-top:10px"><strong>Rule:</strong> OCR price (Arabic/English digits) must equal any <code>final_price</code> fetched for the first-level SKUs.</p>

  <footer style="margin-top:14px;text-align:center;color:#888;font-size:13px">© 2025 Banner Price Checker</footer>
</div>

<script type="module">
/* Load HF Gradio client only over http(s) */
let GradioClient = null, handle_file = null;
if (location.protocol !== 'file:') {
  try {
    const mod = await import('https://cdn.jsdelivr.net/npm/@gradio/client/dist/index.min.js');
    GradioClient = mod.Client; handle_file = mod.handle_file;
  } catch(e) { console.warn('Gradio client load failed:', e); }
}

/* ============ Helpers ============ */
const uniq = a => [...new Set(a)];
function baseApi(u){ return u.split('/api')[0] + '/api/'; }
function deriveStore(u){ try{ return u.split('/')[5] || 'sa_en'; }catch{ return 'sa_en'; } }
const toHyphenStore = s => String(s).replace('_','-');
const clamp=(n,min,max)=>Math.max(min,Math.min(max,n));
function toWesternDigits(s){
  const map = {'٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9'};
  return String(s).replace(/[٠-٩۰-۹]/g, ch => map[ch] ?? ch);
}
const onlyDigits = s => String(s).replace(/[^\d]/g,'');
function normalizePriceNumber(s){
  const western = toWesternDigits(s);
  const dig = onlyDigits(western);
  return dig.replace(/^0+/, '') || dig;
}
function extractPriceCandidatesFromText(t){
  t = toWesternDigits(t).replace(/(?:sar|aed|kwd|qar|bhd?|rs|riyals?|﷼|ر\.?س\.?)/gi,' ')
                        .replace(/cash\s*back|vat|offer|starting\s*from|from|only|save|off/gi,' ');
  const rx = /\b\d{1,3}(?:[,\s]\d{3})+(?:[.,]\d{1,2})?|\b\d{3,7}(?:[.,]\d{1,2})?\b/g;
  return uniq((t.match(rx)||[]).map(normalizePriceNumber).filter(s=>s.length>=3 && s.length<=7));
}

/* ============ Image preprocessing (safe) ============ */
function unsharp(ctx, w, h) {
  const src = ctx.getImageData(0,0,w,h), out = ctx.createImageData(w,h);
  const sd = src.data, od = out.data, amount = 0.6;
  const at = (x,y,c)=>{x=clamp(x,0,w-1);y=clamp(y,0,h-1);return sd[(y*w+x)*4+c];};
  for(let y=0;y<h;y++){
    for(let x=0;x<w;x++){
      for(let c=0;c<3;c++){
        const n = (at(x-1,y-1,c)+at(x,y-1,c)+at(x+1,y-1,c)+at(x-1,y,c)+at(x,y,c)+at(x+1,y,c)+at(x-1,y+1,c)+at(x,y+1,c)+at(x+1,y+1,c))/9;
        const v = at(x,y,c) + amount*(at(x,y,c)-n);
        od[(y*w+x)*4+c] = clamp(v,0,255);
      }
      od[(y*w+x)*4+3] = at(x,y,3);
    }
  }
  ctx.putImageData(out,0,0);
}
function contrastStretch(ctx,w,h){
  const img=ctx.getImageData(0,0,w,h), d=img.data;
  let min=255,max=0;
  for(let i=0;i<d.length;i+=4){
    const g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
    min=Math.min(min,g); max=Math.max(max,g);
  }
  const scale = max>min ? 255/(max-min) : 1;
  for(let i=0;i<d.length;i+=4){
    let g = 0.299*d[i] + 0.587*d[i+1] + 0.114*d[i+2];
    g = (g-min)*scale; d[i]=d[i+1]=d[i+2]=g;
  }
  ctx.putImageData(img,0,0);
}
function adaptiveBinarize(ctx,w,h){
  const img=ctx.getImageData(0,0,w,h), d=img.data;
  let sum=0; for(let i=0;i<d.length;i+=4) sum+=d[i];
  const mean=sum/(d.length/4), k=1.7;
  for(let i=0;i<d.length;i+=4){
    const v = (d[i]-128)*k+128; const b = v>mean?255:0;
    d[i]=d[i+1]=d[i+2]=b;
  }
  ctx.putImageData(img,0,0);
}
async function preprocessToDataURL(blob, {enhance=true}={}){
  const bmp = await createImageBitmap(blob);
  // guarantee workable width 1200–2000 px
  const targetW = Math.min(2000, Math.max(1200, bmp.width));
  const scale = targetW / bmp.width;
  const W = Math.round(bmp.width*scale), H = Math.round(bmp.height*scale);
  const canvas = document.createElement('canvas'); canvas.width=W; canvas.height=H;
  const ctx = canvas.getContext('2d', { willReadFrequently:true });
  ctx.imageSmoothingEnabled = false;
  ctx.drawImage(bmp, 0, 0, W, H);
  if(enhance){ unsharp(ctx,W,H); contrastStretch(ctx,W,H); adaptiveBinarize(ctx,W,H); }
  return canvas.toDataURL('image/png');
}

/* ============ Tesseract Pro (no custom worker) ============ */
const TESS_LANG_PATH="https://tessdata.projectnaptha.com/4.0.0";
async function tesseractPass(img, psm){
  return await Tesseract.recognize(
    img, 'eng+ara',
    {
      langPath: TESS_LANG_PATH,
      tessedit_pageseg_mode: String(psm),
      tessedit_char_whitelist: "0123456789,.٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹",
      classify_bln_numeric_mode: "1",
      user_defined_dpi: "300",
      preserve_interword_spaces: "1"
    }
  );
}
function scoreOcr(text, data){
  const words = data?.words || [];
  const digitWords = words.filter(w => /\d/.test(w.text||""));
  const conf = digitWords.length ? digitWords.reduce((a,w)=>a+(w.confidence||0),0)/digitWords.length : (data?.confidence||0);
  const prices = extractPriceCandidatesFromText(text||'');
  return {score: conf + Math.min(5, prices.length)*3, prices};
}
async function ocrWithTesseractPro(url, enhance){
  const blob = await fetch(url,{mode:'cors'}).then(r=>r.blob());
  const dataUrl = await preprocessToDataURL(blob,{enhance});
  const psms = [6,7,12]; // body text / single line / sparse
  let best={score:-1,prices:[]};
  for(const p of psms){
    const res = await tesseractPass(dataUrl, p);
    const s = scoreOcr(res?.data?.text||'', res?.data);
    if(s.score>best.score) best=s;
  }
  if(best.prices.length===0){
    const res = await tesseractPass(url, 6);
    const s = scoreOcr(res?.data?.text||'', res?.data);
    if(s.score>best.score) best=s;
  }
  return {text:'<<omitted>>', prices:best.prices, provider:'tesseract'};
}

/* ============ HF Space OCR (auto-disabled on file://) ============ */
async function connectSpace(){
  if(!GradioClient) throw new Error('HF disabled on file:// — serve via http(s)');
  const space = document.getElementById('hfSpace').value.trim() || 'merterbak/DeepSeek-OCR-Demo';
  const token = document.getElementById('hfToken').value.trim();
  return await GradioClient.connect(space, token ? { hf_token: token } : {});
}
async function runSpaceOCROnUrl(imageUrl){
  const app = await connectSpace();
  const api = await app.view_api();
  const endpoints=[...Object.keys(api.named_endpoints||{}),...Object.keys(api.unnamed_endpoints||{})];
  if(!endpoints.length) throw new Error('No endpoints exposed by the Space');
  const blob = await fetch(imageUrl,{mode:'cors'}).then(r=>r.blob());
  const file = handle_file(blob);
  for(const api_name of ['/predict','/run', endpoints[0]]){
    try{
      let result = await app.predict(api_name, [file]);
      let text = Array.isArray(result?.data)
                 ? result.data.map(x=> typeof x==='string'?x:(x?.text??x?.value??JSON.stringify(x))).join(' ')
                 : (typeof result?.data==='string' ? result.data : JSON.stringify(result?.data ?? result));
      return { text, prices: extractPriceCandidatesFromText(text), provider:'hf-space' };
    }catch{}
  }
  throw new Error('Space predict failed');
}
async function ocrImage(url, enhance){
  const requested = [...document.querySelectorAll('input[name="ocrProvider"]')].find(r=>r.checked)?.value || 'tesseract';
  if(requested==='hf' && location.protocol!=='file:'){
    try{ return await runSpaceOCROnUrl(url); }catch(e){ console.warn('HF failed → Tesseract:', e); }
  }
  return ocrWithTesseractPro(url, enhance);
}

/* ============ CMS → pairs / SKUs / prices ============ */
function extractImageCmsPairsFromParent(json){
  const items = json?.data?.cms_items?.items || [];
  const pairs=[];
  items.forEach(({item})=>{
    const tokens=item.split('||').map(s=>s.trim());
    for(let i=0;i<tokens.length;i++){
      const tok=tokens[i];
      if(/^https?:\/\/.*\.(?:jpg|jpeg|png|webp)(\?.*)?$/i.test(tok)){
        const info=(tokens[i+1]||'').split(',');
        if(info[0]==='cms' && info[1]) pairs.push({image:tok,cmsSlug:info[1]});
      }
      const seg=tok.split(',');
      if(seg.length>=3 && /^https?:\/\/.*\.(?:jpg|jpeg|png|webp)/i.test(seg[0]) && seg[1]==='cms' && seg[2]) pairs.push({image:seg[0],cmsSlug:seg[2]});
    }
  });
  const ql=json?.data?.quick_links||[];
  ql.forEach(q=> (q.child_items||[]).forEach(ch=>{ if(ch.linkType==='cms'&&ch.linkValue) pairs.push({image:null,cmsSlug:ch.linkValue}); }));
  return pairs;
}
function extractSkusFromChildCMS(json, limit){
  const items=json?.data?.cms_items?.items||[];
  const out=new Set();
  items.forEach(({item})=>{
    const t=item.trim();
    if(/^products\|\|/i.test(t) || /^productlist\|\|/i.test(t)){
      const csv=t.split('||')[1]||''; csv.split(',').map(s=>s.trim()).filter(Boolean).forEach(s=>out.add(s));
    }
    t.split('||').forEach(seg=>{
      const p=seg.split(',');
      if(p[0]==='products' && p[1]) p[1].split(',').forEach(s=>out.add(s.trim()));
      if(p[0]==='product' && p[1]) out.add(p[1].trim());
    });
  });
  return Array.from(out).slice(0,limit);
}
function pickFinalPrices(j){
  const finals=[]; const hits=j?.data?.hits?.hits||j?.hits?.hits||[];
  hits.forEach(h=>{ const src=h._source||h.source||h||{}; if(src.final_price!=null) finals.push(String(src.final_price)); });
  return uniq(finals.map(normalizePriceNumber));
}
async function fetchProductFinalPrices(apiBase, storeHyph, skusCsv){
  const url=`${apiBase}catalogv1/product/store/${storeHyph}/sku/${skusCsv}`;
  const r=await fetch(url); if(!r.ok) throw new Error('HTTP '+r.status);
  const j=await r.json(); return { url, prices: pickFinalPrices(j), raw:j };
}
function decideMatch(ocrNums, finalPrices){
  if(!ocrNums.length) return {status:'UNKNOWN', reason:'No OCR price found'};
  if(!finalPrices.length) return {status:'UNKNOWN', reason:'No final_price found in API'};
  const any = ocrNums.some(x=>finalPrices.includes(x));
  return {status:any?'OK':'NOMATCH', reason:any?'Exact match found (final_price)':'No equal final_price found'};
}

/* ============ Rendering ============ */
function pairRowHTML(row){
  const badge = `<span class="badge ${row.status}">${row.status}</span>`;
  const ocrLine = `<div class="meta"><strong>OCR:</strong> ${row.ocrPrices.length?row.ocrPrices.join(', '):'<em>none</em>'} <span class="ocr-src">[${row.ocrProvider}]</span></div>`;
  const apiLine = row.apiOk
    ? `<div class="meta"><strong>API final_price(s):</strong> ${row.apiPrices.slice(0,20).join(', ') || '<em>none</em>'}</div>`
    : `<div class="meta" style="color:#b00020"><strong>API error:</strong> ${row.apiError}</div>`;
  const imgBlock = row.image ? `<img class="thumb" src="${row.image}" alt="banner">`
                             : `<div class="meta" style="color:#999">No parent image (child CMS may contain images)</div>`;
  return `
    <div class="pair">
      <div class="left">
        <div class="head">Banner</div>
        ${imgBlock}
        <div class="meta"><strong>Image URL:</strong> ${row.image ? `<a href="${row.image}" target="_blank">open</a>` : '-'}</div>
      </div>
      <div class="right">
        <div class="head">Comparison ${badge}</div>
        <div class="kv">
          <div>Parent CMS:</div><div>${row.parentUrl ? `<a href="${row.parentUrl}" target="_blank">${row.parentUrl}</a>` : '-'}</div>
          <div>Child CMS:</div><div>${row.childCmsUrl ? `<a href="${row.childCmsUrl}" target="_blank">${row.childCmsUrl}</a>` : '-'}</div>
          <div>SKU API:</div><div>${row.productApiUrl ? `<a href="${row.productApiUrl}" target="_blank">${row.productApiUrl}</a>` : '-'}</div>
        </div>
        <div class="divider"></div>
        ${ocrLine}
        ${apiLine}
        <div class="meta"><em>${row.reason||''}</em></div>
      </div>
    </div>
  `;
}
function appendPair(row){ document.getElementById('pairs').insertAdjacentHTML('beforeend', pairRowHTML(row)); }
function appendDiscovery(parentUrl, pairs){
  const el=document.createElement('div');
  el.innerHTML=`
  <div class="pair" style="grid-template-columns:1fr">
    <div class="left" style="border-right:none">
      <div class="head">Parent: ${parentUrl}</div>
      <div class="meta"><strong>Found CMS links:</strong> ${pairs.length}</div>
      <div style="margin-top:6px">${pairs.slice(0,50).map(p=>`<span class="pill">${p.cmsSlug||'<em>unknown</em>'}</span>`).join(' ')}${pairs.length>50?' …':''}</div>
    </div>
  </div>`;
  document.getElementById('discover').appendChild(el);
}
function clearResults(){
  document.getElementById('discover').innerHTML='';
  document.getElementById('pairs').innerHTML='';
  document.getElementById('empty').style.display='none';
}

/* ============ Orchestrators ============ */
async function run(){
  clearResults();
  const seeds=document.getElementById('cmsInput').value.trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);
  if(!seeds.length){ alert('Provide at least one CMS page-v2 URL.'); return; }
  document.getElementById('loading').style.display='block';
  const enhance=document.getElementById('imgEnhance').checked;
  const skuLimit=+document.getElementById('skuLimit').value || 24;

  for(const parentUrl of seeds){
    const apiBase=baseApi(parentUrl);
    const store=deriveStore(parentUrl);
    const storeHy=toHyphenStore(store);
    const parentPrefix=parentUrl.substring(0,parentUrl.lastIndexOf('/')+1);

    let parentJson;
    try{ const r=await fetch(parentUrl); if(!r.ok) throw new Error('HTTP '+r.status); parentJson=await r.json(); }
    catch(e){ console.error('Parent fetch failed:', e); continue; }

    const pairs=extractImageCmsPairsFromParent(parentJson);
    appendDiscovery(parentUrl, pairs);

    for(const pair of pairs){
      const childCmsUrl=`${parentPrefix}${pair.cmsSlug}`;
      let ocrPrices=[], ocrProvider='-';
      if(pair.image){
        try{ const o=await ocrImage(pair.image, enhance); ocrPrices=o.prices||[]; ocrProvider=o.provider||'unknown'; }
        catch{ ocrPrices=[]; ocrProvider='error'; }
      }

      let childJson;
      try{
        const r=await fetch(childCmsUrl); if(!r.ok) throw new Error('HTTP '+r.status);
        childJson=await r.json();
      }catch(e){
        appendPair({ image:pair.image,parentUrl,childCmsUrl,productApiUrl:null,
          ocrPrices,ocrProvider,apiOk:false,apiPrices:[],apiError:'Child CMS fetch failed',
          status:'ERROR',reason:'Unable to fetch child CMS' });
        continue;
      }

      const skus=extractSkusFromChildCMS(childJson, skuLimit);
      if(!skus.length){
        appendPair({ image:pair.image,parentUrl,childCmsUrl,productApiUrl:null,
          ocrPrices,ocrProvider,apiOk:true,apiPrices:[],apiError:null,
          status:'UNKNOWN',reason:'No SKUs found in child CMS' });
        continue;
      }

      const skusCsv=skus.join(',');
      let productApiUrl, apiPrices=[], apiOk=false, apiError=null;
      try{
        const got=await fetchProductFinalPrices(apiBase, storeHy, skusCsv);
        productApiUrl=got.url; apiPrices=got.prices; apiOk=true;
      }catch(e){ apiError=String(e); apiOk=false; }

      const decision=decideMatch(ocrPrices, apiPrices);
      appendPair({ image:pair.image,parentUrl,childCmsUrl,productApiUrl,
        ocrPrices,ocrProvider,apiOk,apiPrices,apiError,
        status:decision.status,reason:decision.reason });
    }
  }
  document.getElementById('loading').style.display='none';
  if(!document.querySelector('#pairs .pair')) document.getElementById('empty').style.display='block';
}

async function resolveManualTargetToCmsOrApi(line,parentUrlSample){
  if(/^https?:\/\//i.test(line)) return { cmsUrl:null, apiUrl: line };
  if(!parentUrlSample) return { cmsUrl:null, apiUrl:null };
  const parentPrefix=parentUrlSample.substring(0,parentUrlSample.lastIndexOf('/')+1);
  return { cmsUrl: parentPrefix + line, apiUrl: null };
}
async function runPairs(){
  document.getElementById('loading').style.display='block';
  const txt=document.getElementById('pairsInput').value.trim();
  if(!txt){ alert('Add at least one "image || cmsSlugOrUrl" line.'); document.getElementById('loading').style.display='none'; return; }
  const enhance=document.getElementById('imgEnhance').checked;
  const skuLimit=+document.getElementById('skuLimit').value || 24;
  const parentRef=document.getElementById('cmsInput').value.trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean)[0] || '';

  for(const line of txt.split(/\r?\n/).map(s=>s.trim()).filter(Boolean)){
    const [img,target]=line.split('||').map(s=>s.trim()); if(!img||!target) continue;

    let ocrPrices=[], ocrProvider='-';
    try{ const o=await ocrImage(img, enhance); ocrPrices=o.prices||[]; ocrProvider=o.provider||'unknown'; }
    catch{ ocrPrices=[]; ocrProvider='error'; }

    const { cmsUrl, apiUrl } = await resolveManualTargetToCmsOrApi(target, parentRef);
    const apiBase = parentRef ? baseApi(parentRef) : (apiUrl || '').split('/api/')[0] + '/api/';
    const store   = parentRef ? deriveStore(parentRef) : 'sa_en';
    const storeHy = toHyphenStore(store);

    if(apiUrl){
      let apiPrices=[], apiOk=false, apiError=null;
      try{ const r=await fetch(apiUrl); if(!r.ok) throw new Error('HTTP '+r.status); const j=await r.json(); apiPrices=pickFinalPrices(j); apiOk=true; }
      catch(e){ apiOk=false; apiError=String(e); }
      const decision=decideMatch(ocrPrices, apiPrices);
      appendPair({ image:img,parentUrl:parentRef||null,childCmsUrl:null,productApiUrl:apiUrl,
        ocrPrices,ocrProvider,apiOk,apiPrices,apiError,status:decision.status,reason:decision.reason });
      continue;
    }

    let childJson;
    try{ const r=await fetch(cmsUrl); if(!r.ok) throw new Error('HTTP '+r.status); childJson=await r.json(); }
    catch(e){
      appendPair({ image:img,parentUrl:parentRef||null,childCmsUrl:cmsUrl,productApiUrl:null,
        ocrPrices,ocrProvider,apiOk:false,apiPrices:[],apiError:'Child CMS fetch failed',
        status:'ERROR',reason:'Unable to fetch child CMS' });
      continue;
    }
    const skus=extractSkusFromChildCMS(childJson, skuLimit);
    if(!skus.length){
      appendPair({ image:img,parentUrl:parentRef||null,childCmsUrl:cmsUrl,productApiUrl:null,
        ocrPrices,ocrProvider,apiOk:true,apiPrices:[],apiError:null,status:'UNKNOWN',reason:'No SKUs found in child CMS' });
      continue;
    }
    const skusCsv=skus.join(',');
    let productApiUrl, apiPrices=[], apiOk=false, apiError=null;
    try{ const got=await fetchProductFinalPrices(apiBase, storeHy, skusCsv);
         productApiUrl=got.url; apiPrices=got.prices; apiOk=true; }
    catch(e){ apiOk=false; apiError=String(e); }
    const decision=decideMatch(ocrPrices, apiPrices);
    appendPair({ image:img,parentUrl:parentRef||null,childCmsUrl:cmsUrl,productApiUrl,
      ocrPrices,ocrProvider,apiOk,apiPrices,apiError,status:decision.status,reason:decision.reason });
  }

  document.getElementById('loading').style.display='none';
  if(!document.querySelector('#pairs .pair')) document.getElementById('empty').style.display='block';
}

/* Session & Clipboard */
window.run=run; window.runPairs=runPairs;
window.clearSession=function(){
  document.getElementById('discover').innerHTML='';
  document.getElementById('pairs').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('cmsInput').value='';
  document.getElementById('pairsInput').value='';
  document.getElementById('loading').style.display='none';
};
window.copyVisible=function(){
  const rows=[...document.querySelectorAll('#pairs .pair')].map(p=>p.innerText.replace(/\n{2,}/g,'\n').trim()).join('\n\n---\n\n');
  if(!rows){ alert('Nothing to copy.'); return; }
  navigator.clipboard.writeText(rows).then(()=>alert('Copied!'));
};
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='price_checker'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

PRICE_CHECKER_HTML,
    'products' => <<<'PRODUCTS_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Multi Product / SKU Link Checker</title>
<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --radius:10px;--warn:#8d4a00;--ok:#096b09;--err:#a10000;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
   padding:18px 10px;
 }
 .container{
   width:100%;max-width:980px;background:var(--card);
   border-radius:var(--radius);padding:22px 24px 26px;
   box-shadow:var(--shadow);
 }
 header{display:flex;align-items:center;justify-content:space-between;
         border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:20px}
 header img{width:54px;height:54px}
 header h1{font-size:24px;color:var(--blue);margin:0;font-weight:600}
 label.top-lbl{font-weight:600;font-size:14px;display:block}
 textarea{
   width:100%;height:140px;margin:14px 0 16px;
   padding:10px 12px;border:1px solid #c7cdd4;border-radius:6px;font-size:15px;
   resize:vertical;line-height:1.35;
 }
 .actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:4px}
 button{
   background:var(--blue);color:#fff;border:none;
   padding:10px 16px;border-radius:6px;cursor:pointer;
   font-size:15px;font-weight:600;letter-spacing:.2px;
   transition:background .25s,opacity .25s;
 }
 button:hover{background:#0069d6}
 button.secondary{background:#555}
 button.secondary:hover{background:#333}
 button.success{background:#00906d}
 button.success:hover{background:#007256}
 #loading{display:none;color:#ff9800;font-weight:700;margin:4px 0 10px}
 .output{
   margin-top:4px;padding:18px 18px 16px;background:#f9f9f9;
   border:1px solid #d9dfe5;border-radius:10px;
 }
 .filters{display:flex;flex-wrap:wrap;gap:16px;margin:0 0 14px 0;align-items:flex-end}
 .filters label{font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:6px;min-width:170px}
 select,input{
   padding:7px 9px;font-size:14px;border:1px solid #bbc2c9;border-radius:6px;
   font-family:inherit;background:#fff;
 }
 ul{list-style:none;padding-left:0;margin:0}
 li{
   padding:11px 0 12px;border-bottom:1px solid #e3e7eb;line-height:1.38;
   font-size:14.5px;
 }
 li:last-child{border-bottom:none}
 a{word-break:break-all;text-decoration:none;color:#0645ad}
 a:hover{text-decoration:underline}
 .status-chip{
   display:inline-block;font-size:11px;font-weight:600;
   padding:2px 8px;margin-left:8px;border-radius:12px;vertical-align:middle;
   background:#e0e0e0;color:#333;text-transform:uppercase;letter-spacing:.5px;
 }
 .ok .status-chip{background:#d5f7d5;color:var(--ok)}
 .warning .status-chip{background:#ffe3c4;color:var(--warn)}
 .warningplus .status-chip{background:#ffefd0;color:#6d5400}
 .error .status-chip{background:#ffd4d4;color:var(--err)}
 .meta{
   font-size:12.5px;color:#555;margin-top:4px;line-height:1.4;
   display:flex;flex-wrap:wrap;gap:14px;
 }
 #empty{display:none;color:#d00;font-weight:600;margin-top:6px}
 footer{margin-top:30px;font-size:13px;color:#888;text-align:center}
 .legend{margin-top:16px;font-size:12.5px;line-height:1.5;color:#555}
 .legend-row{display:flex;align-items:center;gap:6px;margin:3px 0}
 .legend-row span.badge{
   display:inline-block;font-size:11px;font-weight:600;padding:2px 7px;border-radius:12px;
 }
 .badge.ok{background:#d5f7d5;color:var(--ok)}
 .badge.warn{background:#ffe3c4;color:var(--warn)}
 .badge.warnplus{background:#ffefd0;color:#6d5400}
 .badge.err{background:#ffd4d4;color:var(--err)}
 @media(max-width:720px){
   header h1{font-size:20px}
   .filters label{min-width:140px}
   .meta{flex-direction:column;align-items:flex-start}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <img src="crawler.png" alt="Logo">
      <h1>Multi Product / SKU Link Checker</h1>
    </header>

    <label for="urls" class="top-lbl">Enter page-v2 URLs (one per line):</label>
    <textarea id="urls" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/12
https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/235"></textarea>

    <div class="actions">
      <button onclick="run()">Fetch JSON & Generate Links</button>
      <button class="secondary" onclick="clearSession()">Clear Session</button>
      <button class="success" onclick="copyVisible()">Copy Visible Links</button>
    </div>
    <div id="loading">Processing… please wait</div>

    <div class="output">
      <div class="filters">
        <label>Status Filter
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">Show All</option>
            <option value="OK">OK</option>
            <option value="Warning">Warning</option>
            <option value="Warning Plus">Warning Plus</option>
            <option value="Error">Error</option>
          </select>
        </label>
        <label>Parent URL Filter
          <input id="parentFilter" placeholder="part of parent URL" oninput="applyFilters()">
        </label>
      </div>

      <ul id="list"></ul>
      <p id="empty">No products found for current filters.</p>

      <div class="legend">
        <div class="legend-row"><span class="badge ok">OK</span> All unique SKUs returned (hits = unique SKUs &gt; 0).</div>
        <div class="legend-row"><span class="badge warn">Warning</span> Partial match: some (not all) unique SKUs returned OR extra hits.</div>
        <div class="legend-row"><span class="badge warnplus">Warning Plus</span> No matching SKUs returned (also shown under “Warning”).</div>
        <div class="legend-row"><span class="badge err">Error</span> Request / parse problem.</div>
      </div>
    </div>
  </div>

  <footer>© 2024 Product Link Checker</footer>

<script>
/* ───── Cookie Helpers (future-proof; currently only used for clearSession completeness) ───── */
function setCookie(n,v,d){const e=new Date(Date.now()+d*864e5).toUTCString();document.cookie=`${n}=${encodeURIComponent(v)};expires=${e};path=/`;}
function getCookie(n){return document.cookie.split('; ').reduce((r,v)=>{const p=v.split('=');return p[0]===n?decodeURIComponent(p[1]):r},'');}
function deleteCookie(n){setCookie(n,'',-1);}

/* ───── State ───── */
let rows=[];                 // {link,parent,totalCount,uniqueCount,hitsCount,status}
let visitedParents=new Set();
let visitedLinks=new Set();  // Avoid duplicate link generation across same session.
const CACHE_COOKIE='multiSkuRows'; // not auto-saved; reserved for future.

function toggleLoading(on){document.getElementById('loading').style.display=on?'block':'none';}

/* ───── Core Run ───── */
async function run(){
  // We KEEP previous results if user runs again to append? Requirement: re-run fresh each fetch.
  // For consistent “session” model we clear runtime each run (except textarea).
  clearRuntime(); 
  toggleLoading(true);

  const seeds=document.getElementById('urls').value
    .trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  if(!seeds.length){
    alert('Please enter at least one valid URL.');
    toggleLoading(false);
    return;
  }

  for(const parent of seeds){
    if(visitedParents.has(parent)) continue;
    visitedParents.add(parent);
    await processParent(parent);
  }

  await validateRows();
  toggleLoading(false);
  render();
  applyFilters();
}

/* ───── Parent Processing ───── */
async function processParent(parent){
  try{
    const res=await fetch(parent);
    if(!res.ok) throw new Error('Network error');
    const json=await res.json();
    const {base,store}=deriveBase(parent);
    const items=parseCMS(json); // [{type:'single'|'multi', list:[...] }]

    items.forEach(({type,list})=>{
      if(!list.length) return;
      const totalCount=list.length;
      const uniqueList=[...new Set(list)];
      const uniqueCount=uniqueList.length;
      const skuSegment=list.join(','); // keep duplicates for request
      const link= type==='multi'
          ? `${base}catalogv2/product/store/${store}/sku/${skuSegment}`
          : `${base}catalogv1/product/store/${store}/sku/${list[0]}`;

      if(visitedLinks.has(link)) return;
      visitedLinks.add(link);

      rows.push({
        link,parent,totalCount,uniqueCount,hitsCount:0,status:''
      });
    });

  }catch(err){
    console.error('Parent fetch failed:', parent, err);
  }
}

/* ───── Helpers ───── */
function deriveBase(url){
  const base=url.split('/api')[0]+'/api/';
  const store=url.split('/')[5].replace('_','-');
  return {base,store};
}

function parseCMS(json){
  const cmsItems=json?.data?.cms_items?.items||[];
  const result=[];
  cmsItems.forEach(({item})=>{
    const tokens=item.split('||');
    for(let i=0;i<tokens.length;i++){
      if(tokens[i]==='product' && tokens[i+1]){
        result.push({type:'single',list:[tokens[i+1]]});
        i++;
      }else if(tokens[i]==='products' && tokens[i+1]){
        const arr=tokens[i+1].split(',').map(s=>s.trim()).filter(Boolean);
        if(arr.length) result.push({type:'multi',list:arr});
        i++;
      }
    }
  });
  return result;
}

/* ───── Validation ───── */
async function validateRows(){
  for(const r of rows){
    try{
      const resp=await fetch(r.link);
      if(!resp.ok){r.status='Error';continue;}
      const j=await resp.json();
      const hits=j?.hits?.hits || j?.data?.hits?.hits || [];
      r.hitsCount=hits.length;

      if(r.hitsCount===0){
        r.status='Warning Plus';
      }else if(r.hitsCount===r.uniqueCount){
        r.status='OK';
      }else if(r.hitsCount<r.uniqueCount){
        r.status='Warning';           // partial
      }else{
        r.status='Warning';           // more hits than requested unique SKUs (still a warning)
      }
    }catch(e){
      r.status='Error';
    }
  }
}

/* ───── Rendering & Filtering ───── */
function render(){
  const ul=document.getElementById('list');
  ul.innerHTML='';
  rows.forEach(r=>{
    const li=document.createElement('li');
    li.dataset.status=r.status;
    li.dataset.parent=r.parent.toLowerCase();

    let cls='error';
    if(r.status==='OK') cls='ok';
    else if(r.status==='Warning') cls='warning';
    else if(r.status==='Warning Plus') cls='warningplus';

    li.className=cls;
    li.innerHTML=`
      <a href="${r.link}" target="_blank">${r.link}</a>
      <span class="status-chip">${r.status}</span>
      <div class="meta">
        <span>Parent: ${r.parent}</span>
        <span>Total SKUs: ${r.totalCount}</span>
        <span>Unique SKUs: ${r.uniqueCount}</span>
        <span>Hits: ${r.hitsCount}</span>
      </div>`;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display=rows.length?'none':'block';
}

function applyFilters(){
  const statusWant=document.getElementById('statusFilter').value;
  const parentTerm=document.getElementById('parentFilter').value.toLowerCase();
  let visible=0;
  document.querySelectorAll('#list li').forEach(li=>{
    const st=li.dataset.status;
    const parent=li.dataset.parent;
    let matchStatus =
       statusWant==='all' ||
       st===statusWant ||
       (statusWant==='Warning' && (st==='Warning' || st==='Warning Plus')); // include Warning Plus
    if(statusWant==='Error') matchStatus = st==='Error';

    const matchParent = !parentTerm || parent.includes(parentTerm);
    const show=matchStatus && matchParent;
    li.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display=visible?'none':'block';
}

function copyVisible(){
  const links=[...document.querySelectorAll('#list li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>li.querySelector('a').href);
  if(!links.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links.join('\n')).then(()=>alert('Copied!'));
}

/* ───── Session Clearing ───── */
function clearRuntime(){
  rows=[];
  visitedParents.clear();
  visitedLinks.clear();
  document.getElementById('list').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
}

function clearSession(){
  deleteCookie(CACHE_COOKIE);   // placeholder if persistence is later added
  clearRuntime();
  document.getElementById('urls').value='';
  toggleLoading(false);
}
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='products'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

PRODUCTS_HTML,
    'sku' => <<<'SKU_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Single SKU Link Checker</title>
<style>
 :root{
   --blue:#007BFF;
   --bg:#f4f7fa;
   --card:#ffffff;
   --radius:10px;
   --shadow:0 4px 12px rgba(0,0,0,.1);
   --ok:#2e7d32;
   --warn:#d84315;
   --err:#b00020;
   --badge:#eceff1;
   --mono:"SFMono-Regular",Consolas,"Liberation Mono",Menlo,monospace;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);
   min-height:100vh;
   display:flex;
   align-items:center;
   justify-content:center;
   padding:18px;
   color:#222;
 }
 .container{
   width:100%;max-width:980px;
   background:var(--card);
   border-radius:var(--radius);
   box-shadow:var(--shadow);
   padding:24px 26px 32px;
   display:flex;
   flex-direction:column;
   gap:18px;
 }
 header{
   display:flex;
   align-items:center;
   justify-content:space-between;
   padding-bottom:12px;
   border-bottom:2px solid var(--blue);
 }
 header h1{font-size:24px;color:var(--blue);margin:0}
 header img{width:54px;height:54px}

 label.main{font-weight:600;font-size:14px}

 textarea{
   width:100%;height:150px;
   padding:12px 14px;
   border:1px solid #cbd3dc;
   border-radius:8px;
   font-size:15px;
   resize:vertical;
 }

 .controls{display:flex;flex-wrap:wrap;gap:10px}
 button{
   background:var(--blue);
   border:none;
   color:#fff;
   padding:10px 18px;
   font-size:15px;
   border-radius:7px;
   cursor:pointer;
   display:inline-flex;
   align-items:center;
   gap:6px;
   line-height:1.05;
   transition:.25s;
 }
 button:hover{background:#005ed0}
 button.secondary{background:#546e7a}
 button.secondary:hover{background:#455a64}
 button.copy{background:#00906d}
 button.copy:hover{background:#007354}

 #loading{display:none;font-weight:600;color:#f90}

 .panel{
   background:#f9fafb;
   border:1px solid #dfe5eb;
   border-radius:10px;
   padding:20px 20px 26px;
 }

 .panel h3{margin:0 0 14px;font-size:20px;color:#333}

 .filters{
   display:flex;
   flex-wrap:wrap;
   gap:14px;
   margin-bottom:12px;
   align-items:flex-end;
 }
 .filters label{
   font-size:14px;
   font-weight:600;
   color:#333;
   display:flex;
   flex-direction:column;
   gap:6px;
 }
 select,input[type=text]{
   min-width:210px;
   padding:8px 10px;
   border:1px solid #bfc9d3;
   border-radius:6px;
   font-size:14px;
   background:#fff;
 }
 input::placeholder{color:#999}

 ul#results{list-style:none;margin:0;padding:0}
 ul#results li{
   padding:10px 10px 12px;
   border-bottom:1px solid #e3e8ee;
   display:flex;
   flex-direction:column;
   gap:4px;
   font-size:14px;
   word-break:break-all;
 }
 ul#results li:last-child{border-bottom:none}

 a{color:#0d47a1;text-decoration:none}
 a:hover{text-decoration:underline}

 .status-badge{
   display:inline-block;
   font-size:11px;
   letter-spacing:.5px;
   font-weight:700;
   padding:3px 8px 4px;
   border-radius:100px;
   background:var(--badge);
   color:#37474f;
   vertical-align:middle;
   font-family:var(--mono);
 }
 .ok   .status-badge{background:var(--ok);color:#fff}
 .warn .status-badge{background:var(--warn);color:#fff}
 .err  .status-badge{background:var(--err);color:#fff}

 .meta-line{font-size:12px;color:#555}
 .empty-msg{color:#d32f2f;font-weight:600;margin:6px 4px 2px}

 .legend{
   margin-top:18px;
   font-size:12.5px;
   line-height:1.5;
   color:#455a64;
   display:flex;
   flex-direction:column;
   gap:4px;
 }
 .legend .row{display:flex;align-items:center;gap:8px}

 footer{margin-top:22px;text-align:center;font-size:13px;color:#888}

 @media(max-width:640px){
   .filters label{width:100%}
   select,input[type=text]{min-width:unset;width:100%}
   button{flex:1 1 auto}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="container">
  <header>
    <img src="crawler.png" alt="Logo">
    <h1>Single SKU Link Checker</h1>
  </header>

  <label for="urlInput" class="main">Enter page-v2 URLs (one per line):</label>
  <textarea id="urlInput" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/123"></textarea>

  <div class="controls">
    <button onclick="run()">Fetch JSON & Build SKU Links</button>
    <button class="secondary" onclick="clearSession()">Clear Session</button>
    <button class="copy" onclick="copyVisible()">Copy Visible Links</button>
  </div>

  <div id="loading">Processing… please wait</div>

  <div class="panel">
    <h3>Generated SKU Links</h3>

    <div class="filters">
      <label>
        Status Filter
        <select id="statusFilter" onchange="applyFilters()">
          <option value="all">Show All</option>
          <option value="OK">OK</option>
          <option value="Warning">Warning</option>
          <option value="Error">Error</option>
        </select>
      </label>
      <label>
        Parent URL Filter
        <input id="parentFilter" type="text" placeholder="part of parent URL" oninput="applyFilters()">
      </label>
    </div>

    <ul id="results"></ul>
    <p id="empty" class="empty-msg" style="display:none;">No SKU links found for current filters.</p>

    <div class="legend">
      <div class="row"><span class="status-badge ok">OK</span> <strong>OK</strong> – Hits array contains one or more products.</div>
      <div class="row"><span class="status-badge warn">WARN</span> <strong>Warning</strong> – Hits array empty for that SKU.</div>
      <div class="row"><span class="status-badge err">ERR</span> <strong>Error</strong> – Fetch / parse issue (shown only in *Show All* or *Error* filter).</div>
    </div>
  </div>

  <footer>© 2024 Single SKU Link Checker</footer>
</div>

<script>
/* ─────────────── State / Persistence ─────────────── */
const rows=[];                  // {link,parent,status}
const seenParents=new Set();    // parent json URLs already fetched
const seenLinks=new Set();      // product detail links
const COOKIE_KEY='singleSkuLinksV1';

/* Cookie helpers */
function setCookie(n,v,d){const e=new Date(Date.now()+d*864e5).toUTCString();document.cookie=`${n}=${encodeURIComponent(v)}; expires=${e}; path=/`;}
function getCookie(n){return document.cookie.split('; ').reduce((r,v)=>{const p=v.split('=');return p[0]===n?decodeURIComponent(p[1]):r},'');}
function deleteCookie(n){setCookie(n,'',-1);}

function saveRows(){setCookie(COOKIE_KEY,JSON.stringify(rows),7);}
function loadRows(){
  const c=getCookie(COOKIE_KEY);
  if(!c) return;
  try{
    JSON.parse(c).forEach(r=>{
      rows.push(r);
      seenParents.add(r.parent);
      seenLinks.add(r.link);
    });
  }catch{}
}

/* ─────────────── Core Flow ─────────────── */
async function run(){
  const seeds=document.getElementById('urlInput').value.trim().split(/\r?\n/).map(x=>x.trim()).filter(Boolean);
  if(!seeds.length){alert('Please enter at least one valid URL.');return;}
  setLoading(true);

  for(const parentUrl of seeds){
    if(seenParents.has(parentUrl)) continue;
    seenParents.add(parentUrl);
    try{
      const r=await fetch(parentUrl);
      if(!r.ok) throw new Error('Network response was not ok');
      const j=await r.json();
      const skus=extractSkus(j);
      const {base1,store}=splitBase(parentUrl);

      if(!skus.length){
        // Insert a placeholder Warning row
        rows.push({link:'No SKU found',parent:parentUrl,status:'Warning'});
        continue;
      }

      skus.forEach(sku=>{
        const link=`${base1}catalogv1/product/store/${store}/sku/${sku}`;
        if(seenLinks.has(link)) return;
        seenLinks.add(link);
        rows.push({link,parent:parentUrl,status:'PENDING'});
      });

    }catch(e){
      rows.push({link:parentUrl,parent:parentUrl,status:'Error'});
      console.error(e);
    }
  }

  await validatePending();
  saveRows();
  setLoading(false);
  render();
  applyFilters();
}

/* ─────────────── Helpers ─────────────── */
function splitBase(u){
  const base1=u.split('/api')[0]+'/api/';
  const store=u.split('/')[5].replace('_','-');
  return {base1,store};
}

function extractSkus(json){
  const items=json?.data?.cms_items?.items||[];
  const out=[];
  items.forEach(({item})=>{
    item.split('||').forEach(entry=>{
      const parts=entry.split(',');
      if(parts[1]==='product' && parts[2]) out.push(parts[2].trim());
    });
  });
  return [...new Set(out.filter(Boolean))];
}

async function validatePending(){
  for(const row of rows){
    if(row.status!=='PENDING') continue;
    try{
      const r=await fetch(row.link);
      if(!r.ok){row.status='Error';continue;}
      const j=await r.json();
      const hits=j?.hits?.hits || j?.data?.hits?.hits || [];
      row.status=hits.length>0?'OK':'Warning';
    }catch{
      row.status='Error';
    }
  }
}

/* ─────────────── UI ─────────────── */
function setLoading(on){document.getElementById('loading').style.display=on?'block':'none';}

function render(){
  const ul=document.getElementById('results');
  ul.innerHTML='';
  rows.forEach(({link,parent,status})=>{
    const li=document.createElement('li');
    li.dataset.status=status;
    li.dataset.parent=parent.toLowerCase();
    let cls=''; if(status==='OK') cls='ok'; else if(status==='Warning') cls='warn'; else if(status==='Error') cls='err';
    li.className=cls;

    if(link.startsWith('http')){
      li.innerHTML=`
        <div>
          <a href="${link}" target="_blank">${link}</a>
          <span class="status-badge ${cls}">${status==='Warning'?'WARN':status.toUpperCase()}</span>
        </div>
        <div class="meta-line">Parent: ${parent}</div>`;
    }else{
      // placeholder (No SKU found)
      li.innerHTML=`<div>${link} <span class="status-badge warn">WARN</span></div>
                    <div class="meta-line">Parent: ${parent}</div>`;
    }
    ul.appendChild(li);
  });
}

/* Filtering */
function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const term=document.getElementById('parentFilter').value.trim().toLowerCase();
  let visible=0;
  document.querySelectorAll('#results li').forEach(li=>{
    const st=li.dataset.status;
    const parent=li.dataset.parent||'';
    let show=false;
    if(want==='all') show=true;
    else if(want==='OK' && st==='OK') show=true;
    else if(want==='Warning' && st==='Warning') show=true;
    else if(want==='Error' && st==='Error') show=true;
    if(show && term && !parent.includes(term)) show=false;
    li.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display=visible?'none':'block';
}

/* Copy visible */
function copyVisible(){
  const txt=[...document.querySelectorAll('#results li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>{
      const a=li.querySelector('a');
      return a?a.href:'';
    })
    .filter(Boolean)
    .join('\n');
  if(!txt){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(txt).then(()=>alert('Copied!'));
}

/* Clear Session */
function clearSession(){
  deleteCookie(COOKIE_KEY);
  rows.length=0;
  seenParents.clear();
  seenLinks.clear();
  document.getElementById('results').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  document.getElementById('urlInput').value='';
  setLoading(false);
}

/* Initial restore */
loadRows();
render();
applyFilters();
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='sku'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

SKU_HTML,
    'stock' => <<<'STOCK_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Stock / Availability Checker</title>

<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --radius:10px;
 }
 *{box-sizing:border-box;margin:0;padding:0}
 body{
   font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
   padding:18px 10px;
 }
 .container{
   width:100%;max-width:980px;background:var(--card);
   border-radius:var(--radius);padding:20px 22px;
   box-shadow:var(--shadow);
 }
 header{display:flex;align-items:center;justify-content:space-between;
         border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:18px}
 header img{width:52px;height:52px}
 header h1{font-size:24px;color:var(--blue);margin:0}
 textarea{
   width:100%;height:140px;margin:14px 0;
   padding:10px 12px;border:1px solid #ccc;border-radius:6px;font-size:15px;
   font-family:inherit;resize:vertical;
 }
 button{
   background:var(--blue);color:#fff;border:none;
   padding:10px 16px;border-radius:6px;cursor:pointer;
   font-size:15px;font-weight:600;letter-spacing:.2px;
   transition:background .25s;
 }
 button:hover{background:#005fbe}
 .actions{display:flex;flex-wrap:wrap;gap:10px}
 #loading{display:none;color:#ff9800;font-weight:700;margin-top:4px}
 .output{margin-top:18px;padding:18px;background:#f9f9f9;border:1px solid #ddd;border-radius:8px}
 .filters{display:flex;flex-wrap:wrap;gap:14px;margin:0 0 14px 0;align-items:flex-end}
 .filters label{font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:4px}
 select,input{
   padding:6px 8px;font-size:14px;border:1px solid #bbb;border-radius:5px;
   min-width:150px;font-family:inherit;
 }
 ul{list-style:none;padding-left:0;margin:0}
 li{padding:10px 0;border-bottom:1px solid #e1e1e1;line-height:1.35}
 li:last-child{border-bottom:none}
 a{word-break:break-all;text-decoration:none;color:#0645ad}
 a:hover{text-decoration:underline}
 .status-chip{
   display:inline-block;font-size:11px;font-weight:600;
   padding:2px 7px;margin-left:6px;border-radius:12px;vertical-align:middle;
   background:#e0e0e0;color:#333;text-transform:uppercase;letter-spacing:.5px;
 }
 .instock .status-chip{background:#d5f7d5;color:#096b09}
 .oos .status-chip{background:#ffd4d4;color:#a10000}
 .error .status-chip{background:#ffd4d4;color:#a10000}
 .meta{font-size:12.5px;color:#555;margin-top:2px}
 #empty{display:none;color:#d00;font-weight:600;margin-top:6px}
 footer{margin-top:26px;font-size:13px;color:#888;text-align:center}
 @media(max-width:640px){
   header h1{font-size:20px}
   .actions button{flex:1 1 auto}
   select,input{min-width:130px}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <img src="crawler.png" alt="Logo">
      <h1>Stock / Availability Checker</h1>
    </header>

    <label for="urls" style="font-weight:600;font-size:14px">Enter page-v2 URLs (one per line):</label>
    <textarea id="urls" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/page-v2/home
https://www.jarir.com/api/v2/ae_en/cmspage/page-v2/12"></textarea>

    <div class="actions">
      <button onclick="run()">Fetch JSON & Check Availability</button>
      <button onclick="clearSearch()" style="background:#555">Clear Search</button>
      <button onclick="copyVisible()" style="background:#00906d">Copy Visible Links</button>
    </div>
    <div id="loading">Processing… please wait</div>

    <div class="output">
      <div class="filters">
        <label>Status Filter
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">Show All</option>
            <option value="In Stock">In Stock</option>
            <option value="Out of Stock">Out of Stock</option>
            <option value="Error">Error</option>
          </select>
        </label>
        <label>Parent URL Filter
          <input id="parentFilter" placeholder="part of parent URL" oninput="applyFilters()">
        </label>
        <label>SKU Filter
          <input id="skuFilter" placeholder="sku code" oninput="applyFilters()">
        </label>
      </div>
      <ul id="list"></ul>
      <p id="empty">No products found for current filters.</p>
      <div style="margin-top:14px;font-size:12.5px;line-height:1.4;color:#555">
        <strong>Status Legend:</strong><br>
        <span class="status-chip instock">In Stock</span> API <code>stock_availablity</code> is <code>AVAILABLE</code>, <code>PREORDER</code>, or <code>BACKORDER</code>.<br>
        <span class="status-chip oos">Out of Stock</span> API <code>stock_availablity</code> is <code>OUTOFSTOCK</code>.<br>
        <span class="status-chip error">Error</span> Fetch / parse problem or unknown availability state.
      </div>
    </div>
  </div>

  <footer>© 2024 Stock / Availability Checker</footer>

<script>
/* ───────── State ───────── */
let rows = [];                 // {sku, link, parent, status, availRaw}
let visitedParents = new Set();

/* ───────── Clear Search ───────── */
function clearSearch(){
  rows = [];
  visitedParents = new Set();
  document.getElementById('urls').value = '';
  const ul=document.getElementById('list');
  ul.innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  document.getElementById('skuFilter').value='';
}

/* ───────── Main Run ───────── */
async function run(){
  // Reset runtime display, but keep the textarea content
  rows = [];
  visitedParents = new Set();
  const ul=document.getElementById('list');
  ul.innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentFilter').value='';
  document.getElementById('skuFilter').value='';

  const seeds = document.getElementById('urls').value
    .trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  if(!seeds.length){ alert('Please enter at least one valid URL.'); return; }

  toggleLoading(true);

  for(const parent of seeds){
    if(visitedParents.has(parent)) continue;
    visitedParents.add(parent);
    await processParent(parent);
  }

  toggleLoading(false);
  render();
  applyFilters();
}

/* ───────── Parent Processing ───────── */
async function processParent(parent){
  try{
    const res = await fetch(parent);
    if(!res.ok) throw new Error('Network error');
    const json = await res.json();

    const {base, baseV2, store} = deriveBase(parent);
    const skus = extractSkus(json);                 // array of SKUs (unique)
    if(!skus.length) return;

    for(const sku of skus){
      const productLink = `${base}catalogv1/product/store/${store}/sku/${sku}`;
      const stockUrl    = `${baseV2}${store.replace('-','_')}/stock/getavailability?skuData=${encodeURIComponent(sku)}|1|0&customer_group=`;

      try{
        const r = await fetch(stockUrl);
        if(!r.ok) throw new Error('Stock API error');
        const data = await r.json();

        const rec = (data?.data?.result && Array.isArray(data.data.result)) ? data.data.result[0] : null;
        const availRaw = (rec?.stock_availablity ?? '').toString().trim().toUpperCase();

        let statusLabel = 'Error';
        switch (availRaw){
          case 'AVAILABLE':
          case 'IN_STOCK':
          case 'IN STOCK':
          case 'PREORDER':      // now treated as In Stock
          case 'BACKORDER':     // now treated as In Stock
            statusLabel = 'In Stock';
            break;
          case 'OUTOFSTOCK':
          case 'OUT_OF_STOCK':
          case 'OUT OF STOCK':
            statusLabel = 'Out of Stock';
            break;
          default:
            statusLabel = 'Error';
        }

        rows.push({ sku, link: productLink, parent, status: statusLabel, availRaw });
      }catch(e){
        rows.push({ sku, link: productLink, parent, status: 'Error', availRaw: '' });
      }
    }
  }catch(err){
    console.error('Parent fetch failed:', parent, err);
  }
}

/* ───────── Helpers ───────── */
function deriveBase(url){
  const root = url.split('/api')[0];        // https://www.jarir.com
  const base  = root + '/api/';             // v1 endpoints
  const baseV2= root + '/api/v2/';          // v2 endpoints (stock)
  const store = url.split('/')[5].replace('_','-'); // for v1 product link
  return { base, baseV2, store };
}

/* Extract SKUs from various CMS patterns:
   - product || <sku>
   - products || <csv skus>
   - new_collection … || category || <catId> || <csv skus>
*/
function extractSkus(json){
  const items = json?.data?.cms_items?.items || [];
  const found = [];

  items.forEach(({item})=>{
    const tokens = item.split('||');

    // 1) product / products tokens
    for(let i=0;i<tokens.length;i++){
      const tok = tokens[i].trim().toLowerCase();
      if(tok==='product' && tokens[i+1]){
        found.push(tokens[i+1].split(',')[0].trim());
        i++;
      }else if(tok==='products' && tokens[i+1]){
        tokens[i+1].split(',').forEach(s=>{ s=s.trim(); if(s) found.push(s); });
        i++;
      }
    }

    // 2) new_collection … || category || <catId> || <csv skus>
    for(let i=0;i<tokens.length;i++){
      if(tokens[i].trim().toLowerCase()==='category'){
        const maybeCsv = tokens[i+2] || '';
        if(maybeCsv && /[0-9,]/.test(maybeCsv)){
          maybeCsv.split(',').forEach(s=>{ s=s.trim(); if(s) found.push(s); });
        }
      }
    }
  });

  // unique, in order
  const seen = new Set();
  const uniq = [];
  for(const s of found){ if(!seen.has(s)){ seen.add(s); uniq.push(s); } }
  return uniq;
}

/* ───────── Rendering & Filters ───────── */
function toggleLoading(on){
  document.getElementById('loading').style.display = on ? 'block':'none';
}

function render(){
  const ul = document.getElementById('list');
  ul.innerHTML = '';
  rows.forEach(r=>{
    const li = document.createElement('li');
    li.dataset.status = r.status;
    li.dataset.parent = r.parent.toLowerCase();
    li.dataset.sku    = r.sku.toLowerCase();

    let cls='error';
    if(r.status==='In Stock') cls='instock';
    else if(r.status==='Out of Stock') cls='oos';

    li.className = cls;
    li.innerHTML = `
      <a href="${r.link}" target="_blank">${r.link}</a>
      <span class="status-chip">${r.status}</span>
      <div class="meta">
        SKU: ${r.sku} | Parent: ${r.parent}
      </div>
    `;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = rows.length ? 'none':'block';
}

function applyFilters(){
  const statusWant = document.getElementById('statusFilter').value;
  const parentTerm = document.getElementById('parentFilter').value.toLowerCase();
  const skuTerm    = document.getElementById('skuFilter').value.toLowerCase();

  let visible = 0;
  document.querySelectorAll('#list li').forEach(li=>{
    const st = li.dataset.status;
    const parent = li.dataset.parent;
    const sku = li.dataset.sku;

    const matchStatus =
      statusWant==='all' || st===statusWant;

    const matchParent = !parentTerm || parent.includes(parentTerm);
    const matchSku    = !skuTerm || sku.includes(skuTerm);

    const show = matchStatus && matchParent && matchSku;
    li.style.display = show ? '' : 'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display = visible ? 'none':'block';
}

function copyVisible(){
  const links = [...document.querySelectorAll('#list li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>li.querySelector('a').href);
  if(!links.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links.join('\n')).then(()=>alert('Copied!'));
}
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='stock'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

STOCK_HTML,
    'sub_category' => <<<'SUB_CATEGORY_HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sub-Category Link Checker</title>
<style>
 :root{
   --blue:#007BFF;--bg:#f4f7fa;--card:#fff;--shadow:0 4px 12px rgba(0,0,0,.1);
   --radius:10px;--ok:#096b09;--warn:#8d4a00;
 }
 *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}
 body{
   background:var(--bg);color:#222;min-height:100vh;
   display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
   padding:18px 10px;
 }
 .container{
   width:100%;max-width:1040px;background:var(--card);
   border-radius:var(--radius);padding:22px 24px 26px;
   box-shadow:var(--shadow);
 }
 header{display:flex;align-items:center;justify-content:space-between;
        border-bottom:2px solid var(--blue);padding-bottom:10px;margin-bottom:20px}
 header img{width:54px;height:54px}
 header h1{font-size:24px;color:var(--blue);margin:0;font-weight:600}
 label.top-lbl{font-weight:600;font-size:14px;display:block}
 textarea{
   width:100%;height:150px;margin:14px 0 16px;
   padding:10px 12px;border:1px solid #c7cdd4;border-radius:6px;font-size:15px;
   resize:vertical;line-height:1.35;
 }
 .actions{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:4px}
 button{
   background:var(--blue);color:#fff;border:none;
   padding:10px 16px;border-radius:6px;cursor:pointer;
   font-size:15px;font-weight:600;letter-spacing:.2px;
   transition:background .25s;
 }
 button:hover{background:#0069d6}
 button.secondary{background:#555}
 button.secondary:hover{background:#333}
 button.success{background:#00906d}
 button.success:hover{background:#007256}
 #loading{display:none;color:#ff9800;font-weight:700;margin:4px 0 10px}
 .output{
   margin-top:4px;padding:18px 18px 16px;background:#f9f9f9;
   border:1px solid #d9dfe5;border-radius:10px;
 }
 .filters{
   display:flex;flex-wrap:wrap;gap:16px;margin:0 0 14px 0;align-items:flex-end
 }
 .filters label{
   font-size:13px;font-weight:600;display:flex;flex-direction:column;gap:6px;
   min-width:170px
 }
 select,input{
   padding:7px 9px;font-size:14px;border:1px solid #bbc2c9;border-radius:6px;
   font-family:inherit;background:#fff;
 }
 ul{list-style:none;padding-left:0;margin:0}
 li{
   padding:11px 0 12px;border-bottom:1px solid #e3e7eb;line-height:1.38;
   font-size:14.5px;
 }
 li:last-child{border-bottom:none}
 a{word-break:break-all;text-decoration:none;color:#0645ad}
 a:hover{text-decoration:underline}
 .status-chip{
   display:inline-block;font-size:11px;font-weight:600;
   padding:2px 8px;margin-left:8px;border-radius:12px;vertical-align:middle;
   background:#e0e0e0;color:#333;text-transform:uppercase;letter-spacing:.5px;
 }
 .ok .status-chip{background:#d5f7d5;color:var(--ok)}
 .warn .status-chip{background:#ffe3c4;color:var(--warn)}
 .meta{
   font-size:12.2px;color:#555;margin-top:4px;line-height:1.35;
 }
 #empty{display:none;color:#d00;font-weight:600;margin-top:6px}
 footer{margin-top:30px;font-size:13px;color:#888;text-align:center}
 .legend{margin-top:16px;font-size:12.5px;line-height:1.45;color:#555}
 .legend-row{display:flex;align-items:center;gap:6px;margin:3px 0}
 .badge{
   display:inline-block;font-size:11px;font-weight:600;padding:2px 7px;border-radius:12px;
 }
 .badge.ok{background:#d5f7d5;color:var(--ok)}
 .badge.warn{background:#ffe3c4;color:var(--warn)}
 @media(max-width:860px){
   .filters label{min-width:150px}
 }
 @media(max-width:640px){
   header h1{font-size:20px}
   .filters label{min-width:140px}
 }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="container">
    <header>
      <img src="crawler.png" alt="Logo">
      <h1>Sub-Category Link Checker</h1>
    </header>

    <label for="urls" class="top-lbl">Enter /getAllCategories URLs (one per line):</label>
    <textarea id="urls" placeholder="https://www.jarir.com/api/v2/sa_en/cmspage/getAllCategories"></textarea>

    <div class="actions">
      <button onclick="run()">Fetch & Generate Links</button>
      <button class="secondary" onclick="clearSession()">Clear Session</button>
      <button class="success" onclick="copyVisible()">Copy Visible Links</button>
    </div>
    <div id="loading">Processing… please wait</div>

    <div class="output">
      <div class="filters">
        <label>Status Filter
          <select id="statusFilter" onchange="applyFilters()">
            <option value="all">Show All</option>
            <option value="OK">OK</option>
            <option value="Warning">Warning</option>
          </select>
        </label>
        <label>Parent URL Filter
          <input id="parentUrlFilter" placeholder="part of parent URL" oninput="applyFilters()">
        </label>
        <label>Parent Title Filter
          <input id="parentTitleFilter" placeholder="parent title" oninput="applyFilters()">
        </label>
        <label>Child Name Filter
          <input id="childNameFilter" placeholder="child name" oninput="applyFilters()">
        </label>
      </div>

      <ul id="list"></ul>
      <p id="empty">No links match current filters.</p>

      <div class="legend">
        <strong>Status Legend:</strong>
        <div class="legend-row"><span class="badge ok">OK</span> Sub-category (child) returns products (hits &gt; 0).</div>
        <div class="legend-row"><span class="badge warn">Warning</span> Sub-category returns no products (empty hits).</div>
      </div>
    </div>
  </div>

  <footer>© 2024 Sub-Category Link Checker</footer>

<script>
/* ───────── Cookie Helpers ───────── */
function setCookie(n,v,d){const e=new Date(Date.now()+d*864e5).toUTCString();document.cookie=`${n}=${encodeURIComponent(v)}; expires=${e}; path=/`;}
function getCookie(n){return document.cookie.split('; ').reduce((r,v)=>{const p=v.split('=');return p[0]===n?decodeURIComponent(p[1]):r},'');}
function deleteCookie(n){setCookie(n,'',-1);}

/* ───────── State ───────── */
let visited = new Set();  // parents + generated child category links
let rows    = [];         // {link,status,parentUrl,parentName,childName}
const CK    = 'scLinks';

/* ───────── Main Run ───────── */
async function run(){
  clearRuntime();  // fresh in-page state
  toggleLoading(true);

  const seeds=document.getElementById('urls').value
    .trim().split(/\r?\n/).map(s=>s.trim()).filter(Boolean);

  if(!seeds.length){
    alert('Please enter at least one valid URL.');
    toggleLoading(false);
    return;
  }

  for(const parentApi of seeds){
    if(visited.has(parentApi)) continue;
    visited.add(parentApi);
    try{
      const res=await fetch(parentApi);
      if(!res.ok) throw new Error('Network response was not ok');
      const data=await res.json();

      const flat=firstLevelCats(data?.data||[]);
      const {basePrefix,store}=splitBase(parentApi);
      const catBase=`${basePrefix}${store}/catalogv1/category/store/${store.replace('_','-')}/category_ids/`;

      flat.forEach(({id,parent_id,parent_name,child_name})=>{
        const link=catBase+id;
        const pUrl=catBase+parent_id;
        if(visited.has(link)) return;
        visited.add(link);
        rows.push({link,status:'',parentUrl:pUrl,parentName:parent_name,childName:child_name});
      });
    }catch(e){
      console.error('Fetch error:',e);
    }
  }

  await validate(rows.map(r=>r.link));
  saveRows();
  toggleLoading(false);
  render();
  applyFilters();
}

/* ───────── Utilities ───────── */
function splitBase(u){
  const p=u.split('/');
  return {basePrefix:p.slice(0,5).join('/')+'/', store:p[5]};
}

function firstLevelCats(arr,out=[]){
  arr.forEach(o=>{
    if(!o?.id) return;
    (o.children_data||[]).forEach(ch=>{
      out.push({id:ch.id,parent_id:o.id,parent_name:o.name,child_name:ch.name});
    });
  });
  return out;
}

async function validate(links){
  for(const l of links){
    try{
      const r=await fetch(l);
      const j=await r.json();
      const ok=(j?.hits?.hits||[]).length>0;
      setStatus(l,ok?'OK':'Warning');
    }catch{
      setStatus(l,'Warning');
    }
  }
}

function setStatus(link,st){
  rows.forEach(r=>{ if(r.link===link) r.status=st; });
}

/* ───────── Persistence ───────── */
function saveRows(){ setCookie(CK,JSON.stringify(rows),7); }
function loadRows(){
  const c=getCookie(CK);
  if(!c) return;
  try{
    JSON.parse(c).forEach(r=>{
      rows.push(r);
      visited.add(r.link);
      visited.add(r.parentUrl);
    });
  }catch{}
}

/* ───────── UI ───────── */
function toggleLoading(on){ document.getElementById('loading').style.display=on?'block':'none'; }

function render(){
  const ul=document.getElementById('list');
  ul.innerHTML='';
  rows.forEach(({link,status,parentUrl,parentName,childName})=>{
    const li=document.createElement('li');
    li.dataset.status=status;
    li.dataset.parenturl=parentUrl.toLowerCase();
    li.dataset.ptitle=parentName.toLowerCase();
    li.dataset.cname=childName.toLowerCase();
    li.className=status==='OK'?'ok':'warn';
    li.innerHTML=`
      <a href="${link}" target="_blank">${link}</a>
      <span class="status-chip">${status}</span>
      <div class="meta">
        Parent URL: ${parentUrl}<br>
        Parent Title: ${parentName} &nbsp;|&nbsp; Child: ${childName}
      </div>
    `;
    ul.appendChild(li);
  });
  document.getElementById('empty').style.display = rows.length ? 'none':'block';
}

function applyFilters(){
  const want=document.getElementById('statusFilter').value;
  const pUrl=document.getElementById('parentUrlFilter').value.toLowerCase();
  const pTit=document.getElementById('parentTitleFilter').value.toLowerCase();
  const cNam=document.getElementById('childNameFilter').value.toLowerCase();
  let visible=0;
  document.querySelectorAll('#list li').forEach(li=>{
    const st=li.dataset.status;
    const matchStatus=(want==='all'||st===want);
    const matchUrl=!pUrl||li.dataset.parenturl.includes(pUrl);
    const matchPT =!pTit||li.dataset.ptitle.includes(pTit);
    const matchCN =!cNam||li.dataset.cname.includes(cNam);
    const show = matchStatus && matchUrl && matchPT && matchCN;
    li.style.display=show?'':'none';
    if(show) visible++;
  });
  document.getElementById('empty').style.display = visible ? 'none':'block';
}

function copyVisible(){
  const links=[...document.querySelectorAll('#list li')]
    .filter(li=>li.style.display!=='none')
    .map(li=>li.querySelector('a').href);
  if(!links.length){alert('Nothing to copy.');return;}
  navigator.clipboard.writeText(links.join('\n')).then(()=>alert('Copied!'));
}

/* Session clearing (consistent naming) */
function clearSession(){
  deleteCookie(CK);
  clearRuntime();
  document.getElementById('urls').value='';
  toggleLoading(false);
}

/* Backwards compatibility (if old code calls clearCache) */
function clearCache(){ clearSession(); }

function clearRuntime(){
  rows=[];
  visited=new Set();
  document.getElementById('list').innerHTML='';
  document.getElementById('empty').style.display='none';
  document.getElementById('statusFilter').value='all';
  document.getElementById('parentUrlFilter').value='';
  document.getElementById('parentTitleFilter').value='';
  document.getElementById('childNameFilter').value='';
}

/* Restore previous session if present */
loadRows();
render();
applyFilters();
</script>
<!-- (optional) identify and/or alias primary action -->
<script> window.QA_TOOL_ID='sub_category'; /* optional */ </script>
<!-- include the bridge at the end -->
<script src="qa_bridge.js"></script>

</body>
</html>

SUB_CATEGORY_HTML,
];

/*********************************
 * 1. DATABASE (MySQL, auto-init)
 *********************************/

const QA_DB_HOST = 'sql309.infinityfree.com';
const QA_DB_PORT = 3306;
const QA_DB_NAME = 'if0_40372489_init_db';
const QA_DB_USER = 'if0_40372489';
const QA_DB_PASS = 'KmUb1Azwzo';

function qa_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . QA_DB_HOST . ';port=' . QA_DB_PORT . ';dbname=' . QA_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, QA_DB_USER, QA_DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Ensure tables exist (idempotent)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_tool_configs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          tool_code VARCHAR(64) NOT NULL,
          config_name VARCHAR(191) NOT NULL,
          config_json MEDIUMTEXT NOT NULL,
          is_enabled TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_test_runs (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          run_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          status VARCHAR(32) NOT NULL,
          total_tests INT NOT NULL DEFAULT 0,
          passed INT NOT NULL DEFAULT 0,
          failed INT NOT NULL DEFAULT 0,
          open_issues INT NOT NULL DEFAULT 0,
          notes TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_run_results (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          run_id INT UNSIGNED NOT NULL,
          tool_code VARCHAR(64) NOT NULL,
          status VARCHAR(32) NOT NULL,
          url TEXT,
          parent TEXT,
          payload MEDIUMTEXT,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_run_tool (run_id, tool_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS qa_users (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          name VARCHAR(191) NOT NULL,
          email VARCHAR(191) NOT NULL,
          role VARCHAR(32) NOT NULL DEFAULT 'tester',
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    return $pdo;
}

/********************
 * 2. SIMPLE JSON API
 ********************/

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['api'];
    $db = qa_db();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    try {
        switch ($action) {
            /* Configs */
            case 'list-configs':
                $stmt = $db->query("SELECT * FROM qa_tool_configs ORDER BY created_at DESC");
                echo json_encode($stmt->fetchAll());
                break;

            case 'save-config':
                $id          = $input['id']          ?? null;
                $tool_code   = $input['tool_code']   ?? '';
                $config_name = $input['config_name'] ?? '';
                $cfg         = $input['config']      ?? [];
                $is_enabled  = !empty($input['is_enabled']) ? 1 : 0;

                if (!$tool_code || !$config_name) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing tool_code or config_name']);
                    break;
                }

                $cfgJson = json_encode($cfg, JSON_UNESCAPED_UNICODE);

                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE qa_tool_configs
                           SET tool_code=?, config_name=?, config_json=?, is_enabled=?
                         WHERE id=?
                    ");
                    $stmt->execute([$tool_code, $config_name, $cfgJson, $is_enabled, $id]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO qa_tool_configs (tool_code, config_name, config_json, is_enabled)
                        VALUES (?,?,?,?)
                    ");
                    $stmt->execute([$tool_code, $config_name, $cfgJson, $is_enabled]);
                    $id = $db->lastInsertId();
                }
                echo json_encode(['ok' => true, 'id' => $id]);
                break;

            case 'delete-config':
                if (!empty($input['id'])) {
                    $stmt = $db->prepare("DELETE FROM qa_tool_configs WHERE id=?");
                    $stmt->execute([$input['id']]);
                }
                echo json_encode(['ok' => true]);
                break;

            /* Users */
            case 'list-users':
                $stmt = $db->query("SELECT * FROM qa_users ORDER BY created_at DESC");
                echo json_encode($stmt->fetchAll());
                break;

            case 'save-user':
                $id    = $input['id']    ?? null;
                $name  = $input['name']  ?? '';
                $email = $input['email'] ?? '';
                $role  = $input['role']  ?? 'tester';
                $is_active = !empty($input['is_active']) ? 1 : 0;

                if (!$name || !$email) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Missing name or email']);
                    break;
                }

                if ($id) {
                    $stmt = $db->prepare("UPDATE qa_users SET name=?, email=?, role=?, is_active=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $is_active, $id]);
                } else {
                    $stmt = $db->prepare("INSERT INTO qa_users (name, email, role, is_active) VALUES (?,?,?,?)");
                    $stmt->execute([$name, $email, $role, $is_active]);
                    $id = $db->lastInsertId();
                }
                echo json_encode(['ok' => true, 'id' => $id]);
                break;

            case 'delete-user':
                if (!empty($input['id'])) {
                    $stmt = $db->prepare("DELETE FROM qa_users WHERE id=?");
                    $stmt->execute([$input['id']]);
                }
                echo json_encode(['ok' => true]);
                break;

            /* Test runs – manual logging only (no wiring to tools) */
            case 'list-runs':
                $stmt = $db->query("SELECT * FROM qa_test_runs ORDER BY run_date DESC LIMIT 50");
                echo json_encode($stmt->fetchAll());
                break;

            case 'run-details':
                $runId = $input['id'] ?? null;
                if (!$runId) {
                    echo json_encode([]);
                    break;
                }
                $stmt = $db->prepare("SELECT tool_code, status, url, parent, payload, created_at FROM qa_run_results WHERE run_id=? ORDER BY tool_code, status, url");
                $stmt->execute([$runId]);
                echo json_encode($stmt->fetchAll());
                break;

            case 'save-run':
                $id       = $input['id']        ?? null;
                $status   = $input['status']    ?? 'completed';
                $total    = (int)($input['total_tests'] ?? 0);
                $passed   = (int)($input['passed']      ?? 0);
                $failed   = (int)($input['failed']      ?? 0);
                $open     = (int)($input['open_issues'] ?? 0);
                $notes    = $input['notes']     ?? '';
                $details  = $input['details']   ?? null;

                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE qa_test_runs
                           SET status=?, total_tests=?, passed=?, failed=?, open_issues=?, notes=?
                         WHERE id=?
                    ");
                    $stmt->execute([$status, $total, $passed, $failed, $open, $notes, $id]);

                    if (is_array($details)) {
                        $del = $db->prepare("DELETE FROM qa_run_results WHERE run_id=?");
                        $del->execute([$id]);
                    }
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO qa_test_runs (status, total_tests, passed, failed, open_issues, notes)
                        VALUES (?,?,?,?,?,?)
                    ");
                    $stmt->execute([$status, $total, $passed, $failed, $open, $notes]);
                    $id = $db->lastInsertId();
                }

                if (is_array($details)) {
                    $ins = $db->prepare("
                        INSERT INTO qa_run_results (run_id, tool_code, status, url, parent, payload)
                        VALUES (?,?,?,?,?,?)
                    ");
                    foreach ($details as $toolBlock) {
                        if (empty($toolBlock['tool_code']) || empty($toolBlock['rows']) || !is_array($toolBlock['rows'])) {
                            continue;
                        }
                        $toolCode = $toolBlock['tool_code'];
                        foreach ($toolBlock['rows'] as $row) {
                            $st  = $row['status'] ?? '';
                            $url = $row['url'] ?? '';
                            $par = $row['parent'] ?? '';
                            $raw = isset($row['payload']) ? $row['payload'] : json_encode($row);
                            $ins->execute([$id, $toolCode, $st, $url, $par, $raw]);
                        }
                    }
                }

                echo json_encode(['ok' => true, 'id' => $id]);
                break;

            case 'delete-run':
                if (!empty($input['id'])) {
                    $stmt = $db->prepare("DELETE FROM qa_run_results WHERE run_id=?");
                    $stmt->execute([$input['id']]);
                    $stmt = $db->prepare("DELETE FROM qa_test_runs WHERE id=?");
                    $stmt->execute([$input['id']]);
                }
                echo json_encode(['ok' => true]);
                break;


        case 'stats':
                $total  = (int)$db->query("SELECT COUNT(*) AS c FROM qa_test_runs")->fetch()['c'];
                $passed = (int)$db->query("SELECT COUNT(*) AS c FROM qa_test_runs WHERE status='passed'")->fetch()['c'];
                $failed = (int)$db->query("SELECT COUNT(*) AS c FROM qa_test_runs WHERE status='failed'")->fetch()['c'];
                $open   = (int)$db->query("SELECT COALESCE(SUM(open_issues),0) AS s FROM qa_test_runs")->fetch()['s'];
                echo json_encode([
                    'total_runs' => $total,
                    'passed'     => $passed,
                    'failed'     => $failed,
                    'open_issues'=> $open,
                ]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['error' => 'Unknown api']);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/********************
 * 3. PAGE RENDERING
 ********************/

$TOOL_DEFS = [
    ['code' => 'brand',           'name' => 'Brand Links'],
    ['code' => 'cms',             'name' => 'CMS Blocks'],
    ['code' => 'category',        'name' => 'Category Links'],
    ['code' => 'category_filter', 'name' => 'Filtered Category'],
    ['code' => 'getcategories',   'name' => 'Get Categories'],
    ['code' => 'images',          'name' => 'Images'],
    ['code' => 'login',           'name' => 'Login'],
    ['code' => 'price_checker',   'name' => 'Price Checker'],
    ['code' => 'products',        'name' => 'Products'],
    ['code' => 'sku',             'name' => 'SKU Lookup'],
    ['code' => 'stock',           'name' => 'Stock / Availability'],
    ['code' => 'sub_category',    'name' => 'Subcategories'],
    ['code' => 'add_to_cart',     'name' => 'Add to Cart'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QA Automation Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<style>
:root{
  --bg:#f4f7fa;--card:#fff;--radius:12px;--shadow:0 4px 12px rgba(0,0,0,.08);
  --blue:#1E88E5;--green:#43A047;--red:#E53935;--amber:#FB8C00;
  --muted:#607D8B;--border:#dde3ec;
}
*{box-sizing:border-box;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}
body{margin:0;background:var(--bg);color:#263238;}
.app-shell{max-width:1200px;margin:0 auto;padding:20px 16px 40px;}
.app-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;}
.app-header h1{font-size:24px;margin:0;color:#1a3a57;}
.app-header small{color:var(--muted);}
.tabs{display:flex;gap:8px;margin-bottom:16px;}
.tab-btn{padding:8px 14px;border-radius:999px;border:1px solid transparent;background:transparent;cursor:pointer;font-size:14px;color:var(--muted);}
.tab-btn.active{background:#e3f2fd;border-color:#90caf9;color:#0d47a1;}
.card-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:18px;}
.charts-section{margin-bottom:18px;}
.charts-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;}
.chart-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:10px 12px;}
.chart-card h3{margin:0 0 6px;font-size:13px;color:var(--muted);}
.chart-card canvas{width:100%;max-height:180px;}
@media(max-width:900px){.charts-grid{grid-template-columns:repeat(1,minmax(0,1fr));}}

.stat-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:12px 14px;border-top:4px solid transparent;}
.stat-card h3{margin:0 0 8px;font-size:14px;color:var(--muted);}
.stat-value{font-size:26px;font-weight:700;}
.stat-meta{font-size:12px;color:var(--muted);}
.stat-total{border-top-color:var(--blue);}
.stat-pass{border-top-color:var(--green);}
.stat-fail{border-top-color:var(--red);}
.stat-open{border-top-color:var(--amber);}
.dashboard-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:16px;margin-bottom:22px;}
.section-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px 16px 16px;}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;}
.section-header h2{margin:0;font-size:16px;}
.section-header small{color:var(--muted);}
.modules-grid{display:flex;gap:10px;overflow-x:auto;padding-bottom:6px;scroll-snap-type:x mandatory;}.modules-grid .module-tile{flex:0 0 220px;scroll-snap-align:start;}
.module-tile{border-radius:10px;padding:12px 12px 10px;background:#e3f2fd;cursor:pointer;position:relative;border:1px solid rgba(0,0,0,.04);transition:.2s;}
.module-tile:nth-child(3n){background:#e8f5e9;}
.module-tile:nth-child(4n){background:#fff3e0;}
.module-tile:nth-child(5n){background:#fce4ec;}
.module-tile.active{transform:translateY(-1px);box-shadow:0 3px 10px rgba(0,0,0,.12);border-color:#90caf9;}
.module-title{font-size:15px;font-weight:600;margin-bottom:4px;}
.module-meta{font-size:12px;color:var(--muted);}
.tool-runner{display:flex;flex-direction:column;height:100%;}
.tool-runner-controls{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;}
.tool-runner-controls button{border-radius:999px;border:none;padding:8px 14px;font-size:13px;cursor:pointer;}
.btn-primary{background:var(--blue);color:#fff;}
.btn-ghost{background:transparent;border:1px solid var(--border);color:var(--muted);}
.btn-primary:disabled{opacity:.6;cursor:default;}
.tool-container{border-radius:10px;border:1px solid var(--border);padding:0;min-height:350px;background:#fafbff;overflow:hidden;}
.tool-iframe{width:100%;height:420px;border:none;}
.table{width:100%;border-collapse:collapse;font-size:13px;}
.table th,.table td{padding:8px;border-bottom:1px solid #e0e6f0;}
.table th{text-align:left;color:var(--muted);font-weight:500;}
.badge{padding:4px 8px;border-radius:999px;font-size:11px;}
.badge-pass{background:#e8f5e9;color:#2e7d32;}
.badge-fail{background:#ffebee;color:#c62828;}
.badge-run{background:#e3f2fd;color:#1565c0;}
.tab-content{display:none;}
.tab-content.active{display:block;}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:10px;}
.form-field label{display:block;font-size:13px;margin-bottom:4px;color:#455a64;}
.form-field input,.form-field select,.form-field textarea{width:100%;padding:7px 9px;border-radius:6px;border:1px solid #cfd8dc;font-size:13px;}
.form-field textarea{min-height:90px;resize:vertical;}
.checkbox-row{display:flex;flex-wrap:wrap;gap:8px;font-size:13px;}
.checkbox-row label{display:flex;align-items:center;gap:4px;}
.actions-row{margin-top:10px;display:flex;gap:8px;align-items:center;}
.table-actions button{border:none;background:transparent;color:#1E88E5;font-size:12px;cursor:pointer;padding:0 4px;}
  .run-console {
    background: #0d1117;
    color: #c9d1d9;
    font-family: 'Consolas', monospace;
    font-size: 13px;
    padding: 12px;
    border-radius: 6px;
    height: 100%;
    overflow-y: auto;
    border: 1px solid #30363d;
  }
  .run-console .log-line { margin-bottom: 4px; border-bottom: 1px solid #21262d; padding-bottom: 2px; }
  .run-console .log-error { color: #ff7b72; font-weight:bold; }
  .run-console .log-success { color: #7ee787; font-weight:bold; }
  .run-console .log-info { color: #a5d6ff; }
  .run-console .log-warn { color: #d29922; }

  /* Modal */
  .modal-overlay {
    display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.6); z-index: 1000; justify-content: center; align-items: center;
  }
  .modal-overlay.active { display: flex; }
  .modal-card {
    background: #fff; width: 90%; max-width: 1000px; height: 90%; max-height: 800px;
    border-radius: 12px; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
  }
  .modal-header {
    padding: 14px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;
    background: #f8fafc;
  }
  .modal-header h3 { margin: 0; font-size: 18px; color: #1a3a57; }
  .modal-body { flex: 1; padding: 0; background: #fafbff; position: relative; }
  .modal-iframe { width: 100%; height: 100%; border: none; }
  .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #666; }

  /* Fullscreen Console Mode */
  /* Fullscreen Console Mode */
  .console-mode .app-shell { display: none !important; }
  #console-overlay { display: none; }
  .console-mode #console-overlay { display: block; height: 100vh; padding: 20px; box-sizing: border-box; }
  .console-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
  .console-header h2 { margin: 0; color: #1a3a57; }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-shell">
  <header class="app-header">
    <div>
      <h1>QA Automation Dashboard</h1>
      <small>All tools & dashboard in a single PHP file</small>
    </div>
  </header>

  <div class="tabs">
    <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
    <button class="tab-btn" data-tab="configs">Configurations</button>
    <button class="tab-btn" data-tab="users">Users</button>
  </div>

  <!-- DASHBOARD TAB -->
  <section id="tab-dashboard" class="tab-content active">
    
    <div class="section-card charts-section">
      <div class="section-header">
        <h2>Run Insights</h2>
        <small>Overview of all saved test runs</small>
      </div>
      <div class="charts-grid">
        <div class="chart-card">
          <h3>Pass vs Fail (Tests)</h3>
          <canvas id="chart-pass-fail"></canvas>
        </div>
        <div class="chart-card">
          <h3>Runs by Status</h3>
          <canvas id="chart-run-status"></canvas>
        </div>
        <div class="chart-card">
          <h3>Recent Pass Rate</h3>
          <canvas id="chart-pass-trend"></canvas>
        </div>
      </div>
      <div class="text-muted" style="margin-top:8px;">
        Charts are aggregated from the data stored in your <strong>Test Runs</strong> table.
      </div>
    </div>

    <div class="card-row">
      <div class="stat-card stat-total">
        <h3>Total Test Runs</h3>
        <div class="stat-value" id="stat-total">0</div>
        <div class="stat-meta">All time</div>
      </div>
      <div class="stat-card stat-pass">
        <h3>Passed</h3>
        <div class="stat-value" id="stat-passed">0</div>
        <div class="stat-meta">Runs marked as passed</div>
      </div>
      <div class="stat-card stat-fail">
        <h3>Failed</h3>
        <div class="stat-value" id="stat-failed">0</div>
        <div class="stat-meta">Runs marked as failed</div>
      </div>
      <div class="stat-card stat-open">
        <h3>Open Issues</h3>
        <div class="stat-value" id="stat-open">0</div>
        <div class="stat-meta">Total open issues across runs</div>
      </div>
    </div>

    <div class="dashboard-grid">
      <div class="section-card">
        <div class="section-header">
          <h2>Test Modules Overview</h2>
          <small>Click a module to open the tool</small>
        </div>
        <div style="padding: 0 16px 8px; display:flex; justify-content:space-between; align-items:center;">
          <button class="btn-small btn-secondary" id="btn-select-all-modules">Select All / None</button>
          <button class="btn-primary" id="btn-run-all">Run All Tests</button>
        </div>
        <div class="modules-grid" id="modules-grid"></div>
        <div class="text-muted" style="margin-top:8px;">
           Selected tools will run in sequence when you click "Run All Tests". Click a tile to run individually.
        </div>
      </div>
    </div>
    


    <!-- Manual logging of test runs -->
    <div class="section-card">
      <div class="section-header">
        <h2>Log Test Run</h2>
        <small>Manual summary after running tools</small>
      </div>
      <div class="log-form">
        <div class="form-field">
          <label>Status</label>
          <select id="run-status">
            <option value="passed">Passed</option>
            <option value="failed">Failed</option>
            <option value="partial">Partial</option>
          </select>
        </div>
        <div class="form-field">
          <label>Total Tests</label>
          <input type="number" id="run-total" value="0">
        </div>
        <div class="form-field">
          <label>Passed</label>
          <input type="number" id="run-passed" value="0">
        </div>
        <div class="form-field">
          <label>Failed</label>
          <input type="number" id="run-failed" value="0">
        </div>
        <div class="form-field">
          <label>Open Issues</label>
          <input type="number" id="run-open" value="0">
        </div>
        <div class="form-field" style="flex:1 1 200px;">
          <label>Notes</label>
          <input type="text" id="run-notes" placeholder="Optional notes / scope">
        </div>
        <button class="btn-primary" id="run-save-btn">Save Run</button>
      </div>

      <table class="table" id="runs-table">
        <thead>
          <tr>
            <th>#</th><th>Date</th><th>Status</th><th>Total</th><th>Passed</th><th>Failed</th><th>Open</th><th>Notes</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      

    </div>
  </section>

  <!-- CONFIGURATION TAB -->
  <section id="tab-configs" class="tab-content">
    <div class="section-card">
      <div class="section-header">
        <h2>Create / Edit Configuration</h2>
        <small>Inputs are stored only; tools read them manually.</small>
      </div>
      <form id="config-form">
        <input type="hidden" id="cfg-id">
        <div class="form-grid">
          <div class="form-field">
            <label>Configuration Name</label>
            <input type="text" id="cfg-name" placeholder="e.g., Daily Brand Link Check">
          </div>
          <div class="form-field">
            <label>Tool</label>
            <select id="cfg-tool-code">
              <?php foreach ($TOOL_DEFS as $t): ?>
                <option value="<?php echo htmlspecialchars($t['code'], ENT_QUOTES); ?>">
                  <?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-grid" style="margin-top:14px;">
          <div class="form-field" style="grid-column:1 / -1;">
            <label>Target URLs / JSON / Inputs</label>
            <textarea id="cfg-inputs" placeholder="Paste any inputs required for the selected tool."></textarea>
          </div>
        </div>

        <div class="actions-row">
          <label style="font-size:13px;display:flex;align-items:center;gap:4px;">
            <input type="checkbox" id="cfg-enabled" checked> Enable this configuration
          </label>
          <button type="button" class="btn-primary" id="cfg-save-btn">Save Configuration</button>
          <button type="button" class="btn-ghost" id="cfg-reset-btn">Reset</button>
        </div>
      </form>
    </div>

    <div class="section-card" style="margin-top:16px;">
      <div class="section-header">
        <h2>Existing Configurations</h2>
        <small>Reference only – open tool and copy values manually.</small>
      </div>
      <table class="table" id="configs-table">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Tool</th><th>Enabled</th><th>Snippet</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>

  <!-- USERS TAB -->
  <section id="tab-users" class="tab-content">
    <div class="section-card">
      <div class="section-header">
        <h2>User Management</h2>
        <small>Metadata only (no auth).</small>
      </div>
      <form id="user-form">
        <input type="hidden" id="user-id">
        <div class="form-grid">
          <div class="form-field">
            <label>Name</label>
            <input type="text" id="user-name" placeholder="Tester name">
          </div>
          <div class="form-field">
            <label>Email</label>
            <input type="email" id="user-email" placeholder="tester@jarir.com">
          </div>
          <div class="form-field">
            <label>Role</label>
            <select id="user-role">
              <option value="tester">Tester</option>
              <option value="admin">Admin</option>
              <option value="viewer">Viewer</option>
            </select>
          </div>
          <div class="form-field">
            <label>Status</label>
            <div class="checkbox-row">
              <label><input type="checkbox" id="user-active" checked> Active</label>
            </div>
          </div>
        </div>
        <div class="actions-row">
          <button type="button" class="btn-primary" id="user-save-btn">Save User</button>
          <button type="button" class="btn-ghost" id="user-reset-btn">Reset</button>
        </div>
      </form>
    </div>

    <div class="section-card" style="margin-top:16px;">
      <div class="section-header">
        <h2>Existing Users</h2>
        <small>Assign testers to runs manually in notes.</small>
      </div>
      <table class="table" id="users-table">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </section>
</div>

<!-- CONSOLE OVERLAY (Focus Mode) -->
<div id="console-overlay">
   <div class="section-card" style="height:100%; display:flex; flex-direction:column;">
     <div class="console-header">
       <h2>Test Execution Log</h2>
       <button class="btn-ghost" onclick="exitConsoleMode()">Close Console</button>
     </div>
     <div id="dash-console" class="run-console"></div>
   </div>
</div>

<!-- MODAL FOR TOOLS -->
<div class="modal-overlay" id="tool-modal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 id="modal-title">Tool Name</h3>
      <button class="modal-close" onclick="closeToolModal()">&times;</button>
    </div>
    <div class="modal-body">
       <iframe id="tool-iframe" class="modal-iframe"></iframe>
    </div>
  </div>
</div>

<!-- RUN SUMMARY MODAL -->
<div class="modal-overlay" id="run-summary-modal">
  <div class="modal-card" style="max-width:500px; max-height:400px; height:auto;">
    <div class="modal-header">
      <h3>Run Complete</h3>
      <button class="modal-close" onclick="closeSummaryModal()">&times;</button>
    </div>
    <div class="modal-body" style="padding:20px; text-align:center;">
      <div style="font-size:48px; margin-bottom:10px;" id="summary-icon">✅</div>
      <h2 id="summary-title" style="margin:0 0 10px;">Run Passed</h2>
      <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; text-align:left; margin:20px 0;">
         <div class="stat-card stat-total" style="text-align:center;">
             <div class="stat-value" id="sum-total">0</div>
             <div class="stat-meta">Total</div>
         </div>
         <div class="stat-card stat-pass" style="text-align:center;">
             <div class="stat-value" id="sum-passed">0</div>
             <div class="stat-meta">Passed</div>
         </div>
         <div class="stat-card stat-fail" style="text-align:center;">
             <div class="stat-value" id="sum-failed">0</div>
             <div class="stat-meta">Failed</div>
         </div>
         <div class="stat-card stat-open" style="text-align:center;">
             <div class="stat-value" id="sum-open">0</div>
             <div class="stat-meta">Open Issues</div>
         </div>
      </div>
      <button class="btn-primary" onclick="closeSummaryModal()">Close</button>
    </div>
  </div>
</div>

<script>
const TOOL_DEFS = <?php echo json_encode($TOOL_DEFS, JSON_UNESCAPED_UNICODE); ?>;
const TOOL_HTML  = <?php echo json_encode($TOOLS_HTML, JSON_UNESCAPED_UNICODE); ?>;

let ACTIVE_TOOL = null;
let CONFIGS = [];
let USERS   = [];
let RUNS    = [];

let chartPassFail = null;
let chartRunStatus = null;
let chartPassTrend = null;

function computeRunAggregates(){
  const agg = {
    totalTests: 0,
    passed: 0,
    failed: 0,
    open: 0,
    statusCounts: {}
  };
  RUNS.forEach(r=>{
    const total = Number(r.total_tests || 0);
    const passed = Number(r.passed || 0);
    const failed = Number(r.failed || 0);
    const open = Number(r.open_issues || 0);
    agg.totalTests += total;
    agg.passed += passed;
    agg.failed += failed;
    agg.open += open;
    const key = ((r.status || 'unknown') + '').toLowerCase();
    agg.statusCounts[key] = (agg.statusCounts[key] || 0) + 1;
  });
  return agg;
}

function updateChartsFromRuns(){
  if (typeof Chart === 'undefined') return;
  const agg = computeRunAggregates();

  const passFailCanvas = document.getElementById('chart-pass-fail');
  if (passFailCanvas){
    const totalForPie = agg.passed + agg.failed + agg.open;
    const data = totalForPie > 0 ? [agg.passed, agg.failed, agg.open] : [0,0,0];
    if (chartPassFail) chartPassFail.destroy();
    chartPassFail = new Chart(passFailCanvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Passed tests','Failed tests','Open issues'],
        datasets: [{ data }]
      },
      options: {
        plugins: { legend: { display: true, position: 'bottom' } },
        maintainAspectRatio: false
      }
    });
  }

  const statusCanvas = document.getElementById('chart-run-status');
  if (statusCanvas){
    const labels = Object.keys(agg.statusCounts);
    const values = labels.map(k=>agg.statusCounts[k]);
    if (chartRunStatus) chartRunStatus.destroy();
    chartRunStatus = new Chart(statusCanvas.getContext('2d'), {
      type: 'pie',
      data: {
        labels,
        datasets: [{ data: values.length ? values : [0] }]
      },
      options: {
        plugins: { legend: { display: true, position: 'bottom' } },
        maintainAspectRatio: false
      }
    });
  }

  const trendCanvas = document.getElementById('chart-pass-trend');
  if (trendCanvas){
    const sorted = RUNS.slice().sort((a,b)=>{
      const da = (a.run_date || '').toString();
      const db = (b.run_date || '').toString();
      return da.localeCompare(db);
    });
    const recent = sorted.slice(-10);
    const labels = recent.map(r=>r.run_date);
    const values = recent.map(r=>{
      const total = Number(r.total_tests || 0);
      const passed = Number(r.passed || 0);
      if (!total) return 0;
      return Math.round((passed / total) * 100);
    });
    if (chartPassTrend) chartPassTrend.destroy();
    chartPassTrend = new Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [{ label: 'Pass rate %', data: values, tension: 0.2 }]
      },
      options: {
        scales: { y: { beginAtZero: true, max: 100 } },
        plugins: { legend: { display: false } },
        maintainAspectRatio: false
      }
    });
  }
}

async function api(action, payload) {
  const res = await fetch('?api=' + encodeURIComponent(action), {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload || {})
  });
  if (!res.ok) throw new Error('API ' + action + ' failed');
  return res.json();
}

/* Tabs */
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
  });
});

/* Modules grid */
const modulesGrid = document.getElementById('modules-grid');
TOOL_DEFS.forEach((t)=>{
  const div = document.createElement('div');
  div.className = 'module-tile';
  div.dataset.code = t.code;
  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
      <div>
        <div class="module-title">${t.name}</div>
        <div class="module-meta">Tool code: ${t.code}</div>
      </div>
      <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#607D8B;">
        <input type="checkbox" class="module-run-checkbox" data-code="${t.code}">
        <span>Run</span>
      </label>
    </div>`;
  div.addEventListener('click', (ev)=>{
    if (ev.target.closest('input[type="checkbox"]')) return;
    selectModule(t.code);
  });
  modulesGrid.appendChild(div);
});

const iframe = document.getElementById('tool-iframe');


function parseConfigObject(cfg) {
  if (!cfg || !cfg.config_json) return {};
  try {
    const obj = JSON.parse(cfg.config_json || '{}');
    return obj && typeof obj === 'object' ? obj : {};
  } catch (e) {
    console.error('Invalid config_json for', cfg.tool_code, e);
    return {};
  }
}

function applyConfigToTool(doc, code, cfgObj) {
  const inputs = (cfgObj.inputs || '').toString();

  switch (code) {
    case 'brand':
    case 'category':
    case 'cms':
    case 'sku': {
      const el = doc.getElementById('urlInput');
      if (el) el.value = inputs;
      break;
    }
    case 'stock':
    case 'getcategories':
    case 'images':
    case 'products':
    case 'sub_category':
    case 'category_filter': {
      const ta = doc.getElementById('urls');
      if (ta) ta.value = inputs;
      break;
    }
    case 'price_checker': {
      const ta = doc.getElementById('cmsInput');
      if (ta) ta.value = inputs;
      break;
    }
    case 'login': {
      const ta = doc.getElementById('bulk');
      if (ta) ta.value = inputs;
      break;
    }
    case 'add_to_cart': {
      const skuTa = doc.getElementById('skus');
      if (skuTa) skuTa.value = inputs;
      if (cfgObj.qty && doc.getElementById('qty')) {
        doc.getElementById('qty').value = cfgObj.qty;
      }
      break;
    }
    default: {
      const ta = doc.querySelector('textarea');
      if (ta) ta.value = inputs;
      break;
    }
  }
}

async function runToolWithConfig(code, cfg) {
  return new Promise((resolve) => {
    const cfgObj = parseConfigObject(cfg);

    function onLoad() {
      iframe.removeEventListener('load', onLoad);

      try {
        const w = iframe.contentWindow;
        const doc = iframe.contentDocument || w.document;

        applyConfigToTool(doc, code, cfgObj);

        let runFn = w.run || w.Run || w.start || w.execute;
        if (!runFn) {
          if (code === 'cms' && typeof w.startCrawling === 'function') {
            runFn = w.startCrawling;
          }
        }
        if (typeof runFn !== 'function') {
          console.warn('No runnable function for tool', code);
          resolve({ tests: 0, passed: 0, failed: 1, open: 1, rows: [] });
          return;
        }

        let finished = false;

        function collectResults() {
          if (finished) return;
          finished = true;

          let tests = 0, passed = 0, failed = 0, open = 0;
          const rowsOut = [];

          if (Array.isArray(w.rows)) {
            w.rows.forEach(r => {
              if (!r) return;
              const status = (r.status || '').toString();
              const url = r.link || r.url || r.href || r.cms || r.endpoint || '';
              const parent = r.parent || r.source || r.origin || '';
              const row = { status, url, parent };
              try {
                row.payload = JSON.stringify(r);
              } catch (e) {
                row.payload = null;
              }
              rowsOut.push(row);

              const s = status.toUpperCase();
              if (!s) return;
              tests++;
              if (s === 'OK' || s === 'VALID' || s === 'SUCCESS' || s === 'IN STOCK') passed++;
              else { failed++; open++; }
            });
          } else {
            const els = doc.querySelectorAll('[data-status]');
            els.forEach(el => {
              const status = (el.getAttribute('data-status') || '').toString();
              const s = status.toUpperCase();
              let url = el.getAttribute('data-url') || '';
              if (!url) {
                const a = el.querySelector('a');
                if (a && a.href) url = a.href;
              }
              const parent = el.getAttribute('data-parent') || '';
              const row = { status, url, parent, payload: null };
              rowsOut.push(row);

              if (!s) return;
              tests++;
              if (s === 'OK' || s === 'VALID' || s === 'SUCCESS' || s === 'IN STOCK') passed++;
              else { failed++; open++; }
            });
          }

          resolve({ tests, passed, failed, open, rows: rowsOut });
        }

        (async () => {
          try {
            const res = runFn();
            if (res && typeof res.then === 'function') {
              await res;
            }

            const loadingEl = doc.getElementById('loading');
            if (loadingEl) {
              const checkDone = () => {
                const style = getComputedStyle(loadingEl);
                if (style.display === 'none' || style.display === '') {
                  setTimeout(collectResults, 300);
                } else {
                  setTimeout(checkDone, 400);
                }
              };
              checkDone();
            } else {
              setTimeout(collectResults, 2000);
            }
          } catch (e) {
            console.error(e);
            resolve({ tests: 0, passed: 0, failed: 1, open: 1, rows: [] });
          }
        })();
      } catch (e) {
        console.error(e);
        resolve({ tests: 0, passed: 0, failed: 1, open: 1, rows: [] });
      }
    }

    iframe.addEventListener('load', onLoad);
    loadToolIntoIframe(code);
  });
}

function loadToolIntoIframe(code) {
  const html = TOOL_HTML[code];
  if (!html) {
    iframe.srcdoc = `<html><body style="font-family:sans-serif;padding:12px;">
      <p>No HTML embedded for tool: <b>${code}</b>.</p>
    </body></html>`;
    return;
  }
  iframe.srcdoc = html;
}

/* ───────── UI Controls ───────── */
function openToolModal(code) {
  const def = TOOL_DEFS.find(t=>t.code===code);
  document.getElementById('modal-title').innerText = def ? def.name : code;
  const modal = document.getElementById('tool-modal');
  modal.classList.add('active');
  loadToolIntoIframe(code);
}

function closeToolModal() {
  document.getElementById('tool-modal').classList.remove('active');
  iframe.srcdoc = ''; // clear
}

function selectModule(code){
  openToolModal(code);
}

/* Open all tools quickly (no automation, just navigation) */
document.getElementById('btn-select-all-modules').addEventListener('click', ()=>{
  const boxes = document.querySelectorAll('.module-run-checkbox');
  const allChecked = [...boxes].every(b=>b.checked);
  boxes.forEach(b => b.checked = !allChecked);
});

function enterConsoleMode() {
  document.body.classList.add('console-mode');
  const c = document.getElementById('dash-console');
  c.innerHTML = ''; 
  logToConsole('Console Mode Active. Initializing...', 'info');
}

function exitConsoleMode() {
  document.body.classList.remove('console-mode');
}
window.exitConsoleMode = exitConsoleMode;
window.closeToolModal = closeToolModal;

function logToConsole(msg, type='info') {
  const c = document.getElementById('dash-console');
  // c.style.display = 'block'; // Always block in focus mode
  const div = document.createElement('div');
  div.className = 'log-line log-' + type;
  div.innerText = `[${new Date().toLocaleTimeString()}] ${msg}`;
  c.appendChild(div);
  c.scrollTop = c.scrollHeight;
}

function closeSummaryModal(){
  document.getElementById('run-summary-modal').classList.remove('active');
}
window.closeSummaryModal = closeSummaryModal;

document.getElementById('btn-run-all').addEventListener('click', async ()=>{
  // Collect selected tools from checkboxes
  const selectedCodes = [...document.querySelectorAll('.module-run-checkbox:checked')].map(cb=>cb.dataset.code);
  if (!selectedCodes.length) {
    alert('Please select at least one module to run using the checkboxes.');
    return;
  }

  // ENTER FOCUS MODE
  enterConsoleMode();
  logToConsole(`Starting Run All... Selected tools: ${selectedCodes.length}`);

  const btn = document.getElementById('btn-run-all');
  btn.disabled = true;

  let totalTests = 0, totalPassed = 0, totalFailed = 0, totalOpen = 0;
  const allDetails = [];

  for (const code of selectedCodes) {
    logToConsole(`Preparing ${code}...`, 'info');
    
    // Check Config
    const cfg = CONFIGS.find(c=>c.tool_code===code && c.is_enabled==1);
    
    // Validate Input existence
    let inputsValid = false;
    if (cfg) {
      try {
        const parsed = JSON.parse(cfg.config_json || '{}');
        const inp = (parsed.inputs || '').trim();
        if (inp.length > 0) inputsValid = true;
      } catch(e){}
    }
    
    if (!cfg || !inputsValid) {
       logToConsole(`SKIPPED ${code}: Missing or empty configuration inputs.`, 'error');
       totalFailed++; 
       totalOpen++;
       continue;
    }

    try {
      logToConsole(`Running ${code}...`, 'info');
      const result = await runToolWithConfig(code, cfg);
      totalTests  += result.tests || 0;
      totalPassed += result.passed || 0;
      totalFailed += result.failed || 0;
      totalOpen   += result.open || 0;
      allDetails.push({
        tool_code: code,
        rows: result.rows || []
      });
      
      // LOG DETAILS
      if(result.rows && result.rows.length){
         result.rows.forEach(r => {
             const s = r.status.toUpperCase();
             const isFail = ['FAILED','ERROR','OUT OF STOCK'].includes(s);
             const type = isFail ? 'warn' : 'success';
             const icon = isFail ? '❌' : '✅';
             logToConsole(`  -> ${icon} [${s}] ${r.url}`, type);
         });
      }

      if(result.failed > 0) {
         logToConsole(`${code} Finished: ${result.passed} Pass, ${result.failed} Fail`, 'error');
      } else {
         logToConsole(`${code} Finished: ${result.passed} Pass, ${result.failed} Fail`, 'success');
      }
      
    } catch (e) {
      console.error('Error running tool', code, e);
      logToConsole(`Error running ${code}: ${e.message}`, 'error');
      totalFailed += 1;
      totalOpen   += 1;
    }
  }

  const status = totalFailed > 0 ? 'failed' : 'passed';
  const notes = 'Run All Tests via dashboard (tools: ' + selectedCodes.join(', ') + ')';

  try {
    logToConsole('Saving Run Report...', 'info');
    await api('save-run', {
      status: status,
      total_tests: totalTests,
      passed: totalPassed,
      failed: totalFailed,
      open_issues: totalOpen,
      notes: notes,
      details: allDetails
    });
    await Promise.all([loadRuns(), loadStats()]);
    logToConsole('Run Saved Successfully.', 'success');
    logToConsole('Run All Tests completed.', 'info');
    
    // Auto-close console and show summary
    setTimeout(()=>{
        exitConsoleMode();
        
        // Show Summary Modal
        document.getElementById('sum-total').innerText = totalTests;
        document.getElementById('sum-passed').innerText = totalPassed;
        document.getElementById('sum-failed').innerText = totalFailed;
        document.getElementById('sum-open').innerText = totalOpen;
        
        const title = document.getElementById('summary-title');
        const icon = document.getElementById('summary-icon');
        if(totalFailed > 0){
            title.innerText = 'Run Completed with Errors';
            title.style.color = '#d32f2f';
            icon.innerText = '⚠️';
        } else {
            title.innerText = 'Run Passed Successfully';
            title.style.color = '#2e7d32';
            icon.innerText = '✅';
        }
        
        document.getElementById('run-summary-modal').classList.add('active');
        
    }, 1500); // Short delay to let user see "Saved" message

  } catch (e) {
    console.error(e);
    logToConsole('Error Saving Run: ' + e.message, 'error');
    alert('Error saving run: ' + e.message);
  } finally {
    btn.disabled = false;
  }
});

/* Test runs */
async function loadRuns(){
  RUNS = await api('list-runs');
  updateChartsFromRuns();
  const tbody = document.querySelector('#runs-table tbody');
  tbody.innerHTML = '';
  RUNS.forEach(r=>{
    const tr = document.createElement('tr');
    tr.dataset.id = r.id;
    tr.innerHTML = `
      <td>${r.id}</td>
      <td>${r.run_date}</td>
      <td>${r.status}</td>
      <td>${r.total_tests}</td>
      <td>${r.passed}</td>
      <td>${r.failed}</td>
      <td>${r.open_issues}</td>
      <td>${r.notes ? r.notes : ''}</td>
      <!-- existing actions, e.g. delete -->
      <td class="table-actions"><button data-action="delete">Delete</button></td>
      <!-- NEW: Report link -->
      <td><a href="qa_run_report.php?run_id=${r.id}" target="_blank" class="btn-small btn-secondary">Report</a></td>`;
    tbody.appendChild(tr);
  });
}

async function showRunDetails(runId){
  const panel = document.getElementById('run-details-panel');
  const container = document.getElementById('run-details-content');
  if (!panel || !container) return;

  container.innerHTML = '<p class="text-muted">Loading run details...</p>';
  panel.style.display = 'block';

  try{
    const rows = await api('run-details',{id: runId});
    if (!rows || !rows.length) {
      container.innerHTML = '<p class="text-muted">No detailed link data stored for this run.</p>';
      return;
    }
    const header = '<table class="run-details-table"><thead><tr><th>Tool</th><th>Status</th><th>URL</th><th>Parent</th></tr></thead><tbody>';
    const body = rows.map(r=>{
      const status = (r.status || '').toString();
      let badgeClass = 'run-details-badge';
      const upper = status.toUpperCase();
      if (upper === 'OK') badgeClass += ' ok';
      else badgeClass += ' fail';
      const safeTool = (r.tool_code || '').toString();
      const safeUrl = (r.url || '').toString();
      const safeParent = (r.parent || '').toString();
      return `<tr>
        <td>${safeTool}</td>
        <td><span class="${badgeClass}">${status}</span></td>
        <td>${safeUrl}</td>
        <td>${safeParent}</td>
      </tr>`;
    }).join('');
    container.innerHTML = header + body + '</tbody></table>';
  }catch(e){
    console.error(e);
    container.innerHTML = '<p class="text-danger">Failed to load run details: ' + e.message + '</p>';
  }
}

document.querySelector('#runs-table').addEventListener('click', async (e)=>{
  const btn = e.target.closest('button');
  if (btn && btn.dataset.action === 'delete') {
    const tr = btn.closest('tr'); const id = parseInt(tr.dataset.id,10);
    if(!confirm('Delete this test run?')) return;
    await api('delete-run',{id:id});
    await Promise.all([loadRuns(), loadStats()]);
    const panel = document.getElementById('run-details-panel');
    if (panel) panel.style.display = 'none';
    return;
  }

  const tr = e.target.closest('tr');
  if (!tr || !tr.dataset.id) return;
  const runId = parseInt(tr.dataset.id,10);
  if (!runId) return;
  await showRunDetails(runId);
});

document.getElementById('run-save-btn').addEventListener('click', async ()=>{
  const status = document.getElementById('run-status').value;
  const total  = parseInt(document.getElementById('run-total').value || '0',10);
  const passed = parseInt(document.getElementById('run-passed').value || '0',10);
  const failed = parseInt(document.getElementById('run-failed').value || '0',10);
  const open   = parseInt(document.getElementById('run-open').value || '0',10);
  const notes  = document.getElementById('run-notes').value.trim();

  await api('save-run',{
    status:status,total_tests:total,passed:passed,failed:failed,open_issues:open,notes:notes
  });

  document.getElementById('run-total').value = 0;
  document.getElementById('run-passed').value = 0;
  document.getElementById('run-failed').value = 0;
  document.getElementById('run-open').value = 0;
  document.getElementById('run-notes').value = '';

  await Promise.all([loadRuns(), loadStats()]);
});

/* Configs */
async function loadConfigs(){
  CONFIGS = await api('list-configs');
  const tbody = document.querySelector('#configs-table tbody');
  tbody.innerHTML = '';
  CONFIGS.forEach((cfg,i)=>{
    let snippet = '';
    try{
      const json = JSON.parse(cfg.config_json || '{}');
      snippet = (json.inputs || '').toString().slice(0,60).replace(/\s+/g,' ');
    }catch(e){}
    const tr = document.createElement('tr');
    tr.dataset.id = cfg.id;
    tr.innerHTML = `
      <td>${i+1}</td>
      <td>${cfg.config_name}</td>
      <td>${cfg.tool_code}</td>
      <td>${cfg.is_enabled ? 'Yes' : 'No'}</td>
      <td>${snippet}</td>
      <td class="table-actions">
        <button data-action="edit">Edit</button>
        <button data-action="delete">Delete</button>
      </td>`;
    tbody.appendChild(tr);
  });
}

document.querySelector('#configs-table').addEventListener('click', async (e)=>{
  const btn = e.target.closest('button'); if(!btn) return;
  const tr = btn.closest('tr'); const id = parseInt(tr.dataset.id,10);
  const cfg = CONFIGS.find(c=>c.id==id); if(!cfg) return;

  if(btn.dataset.action==='edit'){
    document.getElementById('cfg-id').value = cfg.id;
    document.getElementById('cfg-name').value = cfg.config_name;
    document.getElementById('cfg-tool-code').value = cfg.tool_code;
    document.getElementById('cfg-enabled').checked = !!cfg.is_enabled;
    try{
      const json = JSON.parse(cfg.config_json || '{}');
      document.getElementById('cfg-inputs').value = json.inputs || '';
    }catch(e){}
  } else if(btn.dataset.action==='delete'){
    if(!confirm('Delete configuration "'+cfg.config_name+'"?')) return;
    await api('delete-config',{id:cfg.id});
    await loadConfigs();
  }
});

document.getElementById('cfg-save-btn').addEventListener('click', async ()=>{
  const id   = document.getElementById('cfg-id').value || null;
  const name = document.getElementById('cfg-name').value.trim();
  const tool = document.getElementById('cfg-tool-code').value;
  const inputs = document.getElementById('cfg-inputs').value.trim();
  const enabled = document.getElementById('cfg-enabled').checked;

  if(!name || !tool){
    alert('Configuration name and tool are required.');
    return;
  }

  await api('save-config',{
    id:id, tool_code:tool, config_name:name,
    config:{inputs:inputs}, is_enabled:enabled ? 1 : 0
  });

  document.getElementById('config-form').reset();
  document.getElementById('cfg-id').value = '';
  document.getElementById('cfg-enabled').checked = true;
  await loadConfigs();
});

document.getElementById('cfg-reset-btn').addEventListener('click', ()=>{
  document.getElementById('config-form').reset();
  document.getElementById('cfg-id').value = '';
  document.getElementById('cfg-enabled').checked = true;
});

/* Users */
async function loadUsers(){
  USERS = await api('list-users');
  const tbody = document.querySelector('#users-table tbody');
  tbody.innerHTML = '';
  USERS.forEach((u,i)=>{
    const tr = document.createElement('tr');
    tr.dataset.id = u.id;
    tr.innerHTML = `
      <td>${i+1}</td>
      <td>${u.name}</td>
      <td>${u.email}</td>
      <td>${u.role}</td>
      <td>${u.is_active ? 'Active' : 'Inactive'}</td>
      <td class="table-actions">
        <button data-action="edit">Edit</button>
        <button data-action="delete">Delete</button>
      </td>`;
    tbody.appendChild(tr);
  });
}

document.querySelector('#users-table').addEventListener('click', async (e)=>{
  const btn = e.target.closest('button'); if(!btn) return;
  const tr = btn.closest('tr'); const id = parseInt(tr.dataset.id,10);
  const u = USERS.find(x=>x.id==id); if(!u) return;

  if(btn.dataset.action==='edit'){
    document.getElementById('user-id').value = u.id;
    document.getElementById('user-name').value = u.name;
    document.getElementById('user-email').value = u.email;
    document.getElementById('user-role').value = u.role;
    document.getElementById('user-active').checked = !!u.is_active;
  } else if(btn.dataset.action==='delete'){
    if(!confirm('Delete user \"'+u.name+'\"?')) return;
    await api('delete-user',{id:u.id});
    await loadUsers();
  }
});

document.getElementById('user-save-btn').addEventListener('click', async ()=>{
  const id    = document.getElementById('user-id').value || null;
  const name  = document.getElementById('user-name').value.trim();
  const email = document.getElementById('user-email').value.trim();
  const role  = document.getElementById('user-role').value;
  const active= document.getElementById('user-active').checked;

  if(!name || !email){
    alert('Name and email are required.');
    return;
  }

  await api('save-user',{id:id,name:name,email:email,role:role,is_active:active?1:0});

  document.getElementById('user-form').reset();
  document.getElementById('user-id').value='';
  document.getElementById('user-active').checked=true;
  await loadUsers();
});

document.getElementById('user-reset-btn').addEventListener('click', ()=>{
  document.getElementById('user-form').reset();
  document.getElementById('user-id').value='';
  document.getElementById('user-active').checked=true;
});

/* Stats */
async function loadStats(){
  const s = await api('stats');
  document.getElementById('stat-total').textContent  = s.total_runs ?? 0;
  document.getElementById('stat-passed').textContent = s.passed ?? 0;
  document.getElementById('stat-failed').textContent = s.failed ?? 0;
  document.getElementById('stat-open').textContent   = s.open_issues ?? 0;
}

/* Initial */
Promise.all([loadConfigs(), loadUsers(), loadRuns(), loadStats()])
  .catch(console.error);
</script>
</body>
</html>
