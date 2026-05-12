// js/dashboard.js - Dashboard Logic

const state = {
    date: new Date().toISOString().split('T')[0], // YYYY-MM-DD
    stores: {}, // Will hold status and counts
    autoRefresh: true,
    refreshInterval: 1800, // 30 minutes in seconds
    countdown: 1800,
    timer: null,
    currentFilter: 'all'
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Theme Toggle Logic
    const themeToggle = document.getElementById('theme-toggle');
    const iconMoon = document.getElementById('theme-icon-moon');
    const iconSun = document.getElementById('theme-icon-sun');
    
    // Check saved theme
    const savedTheme = localStorage.getItem('pos-theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    themeToggle.addEventListener('click', () => {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('pos-theme', newTheme);
        updateThemeIcon(newTheme);
    });

    function updateThemeIcon(theme) {
        if (theme === 'dark') {
            iconMoon.style.display = 'none';
            iconSun.style.display = 'block';
        } else {
            iconMoon.style.display = 'block';
            iconSun.style.display = 'none';
        }
    }

    // Set up date picker
    const datePicker = document.getElementById('date-picker');
    datePicker.value = state.date;
    
    // Only update state when date picker changes, but don't fetch yet
    datePicker.addEventListener('change', (e) => {
        state.date = e.target.value;
    });

    // Explicit Apply button
    document.getElementById('btn-apply-filter').addEventListener('click', () => {
        fetchAllData(false); // Force fresh fetch
    });
    
    // Set up tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            setFilter(e.target.dataset.filter, e.target);
        });
    });
    
    // Set up search bar
    const searchInput = document.getElementById('store-search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            filterStores();
        });
    }
    
    // Auto refresh toggle
    const refreshToggle = document.getElementById('auto-refresh-toggle');
    refreshToggle.addEventListener('change', (e) => {
        state.autoRefresh = e.target.checked;
        if (state.autoRefresh) {
            startTimer();
        } else {
            clearInterval(state.timer);
            document.getElementById('countdown').textContent = '--';
        }
    });

    // Close Modals
    document.getElementById('modal-close').addEventListener('click', closeModal);
    document.getElementById('modal-overlay').addEventListener('click', (e) => {
        if (e.target.id === 'modal-overlay') closeModal();
    });
    document.getElementById('modal-central-close').addEventListener('click', closeCentralModal);
    document.getElementById('modal-central-overlay').addEventListener('click', (e) => {
        if (e.target.id === 'modal-central-overlay') closeCentralModal();
    });

    // Initial Fetch
    fetchAllData(true);
    startTimer();
});

// Timer for auto-refresh
function startTimer() {
    clearInterval(state.timer);
    state.countdown = state.refreshInterval;
    updateCountdownDisplay();
    
    state.timer = setInterval(() => {
        if (!state.autoRefresh) return;
        
        state.countdown--;
        updateCountdownDisplay();
        
        if (state.countdown <= 0) {
            fetchAllData(true); // useCache = true
            state.countdown = state.refreshInterval;
        }
    }, 1000);
}

function updateCountdownDisplay() {
    const minutes = Math.floor(state.countdown / 60);
    const seconds = state.countdown % 60;
    const formatted = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
    document.getElementById('countdown').textContent = formatted;
}

// Fetch Data
async function fetchAllData(useCache) {
    // 1. Get Server Status (MUST await this so state.stores is populated)
    await fetchStatus();
    
    // 2. Get Invoice Counts and Sync Status concurrently
    await Promise.all([
        fetchCounts(useCache),
        fetchSyncStatus(useCache)
    ]);
    
    // Hide Loading Overlay after initial load
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.transform = 'translateY(20px)';
        setTimeout(() => {
            overlay.style.visibility = 'hidden';
            overlay.style.display = 'none';
        }, 400);
    }
}

async function fetchStatus() {
    try {
        const response = await fetch(`api/server_status.php`);
        const data = await response.json();
        
        let onlineCount = 0;
        let offlineCount = 0;
        
        for (const [code, info] of Object.entries(data)) {
            if (!state.stores[code]) state.stores[code] = {};
            state.stores[code].online = info.online;
            
            if (info.online) onlineCount++;
            else offlineCount++;
            
            updateCardStatus(code);
        }
        
        document.getElementById('val-online').textContent = onlineCount;
        document.getElementById('val-offline').textContent = offlineCount;
        
    } catch (e) {
        console.error('Error fetching server status:', e);
    }
}

async function fetchCounts(useCache) {
    // Show skeletons
    document.querySelectorAll('.invoice-count').forEach(el => el.classList.add('skeleton'));
    
    const promises = Object.keys(state.stores).map(async (code) => {
        try {
            const cacheParam = useCache ? '&use_cache=true' : `&_t=${Date.now()}`;
            const response = await fetch(`api/store_invoice_count.php?store_code=${code}&date=${state.date}${cacheParam}`);
            const data = await response.json();
            
            const info = data[code];
            if (info) {
                state.stores[code].count = info.count;
                state.stores[code].central_count = info.central_count;
                state.stores[code].store_alerts = info.store_alerts;
                state.stores[code].central_alerts = info.central_alerts;
                
                const countEl = document.getElementById(`count-${code}`);
                if (countEl) {
                    const localText = info.count !== null ? info.count : '-';
                    const centralText = info.central_count !== null ? info.central_count : '-';
                    // Using var(--success) for central invoice as requested
                    countEl.innerHTML = `${localText} <span style="color: #a0aec0;">/</span> <span style="color: var(--success);">${centralText}</span>`;
                    countEl.classList.remove('skeleton');
                }
                
                const alertsEl = document.getElementById(`alerts-${code}`);
                if (alertsEl) {
                    const localAlert = info.store_alerts !== null ? info.store_alerts : '-';
                    const centralAlert = info.central_alerts !== null ? info.central_alerts : '-';
                    alertsEl.innerHTML = `<span style="color: var(--text-primary);">${localAlert}</span> <span style="color: #a0aec0;">/</span> <span style="color: red;">${centralAlert}</span>`;
                    alertsEl.classList.remove('skeleton');
                }
            }
        } catch (e) {
            const countEl = document.getElementById(`count-${code}`);
            if (countEl) countEl.classList.remove('skeleton');
        }
    });
    
    await Promise.all(promises);
}

async function fetchSyncStatus(useCache) {
    // Make parallel async requests to each store to avoid 30s timeout PHP errors!
    const promises = Object.keys(state.stores).map(async (code) => {
        try {
            const cacheParam = useCache ? '&use_cache=true' : `&_t=${Date.now()}`;
            const response = await fetch(`api/store_sync_status.php?store_code=${code}&date=${state.date}${cacheParam}`);
            const data = await response.json();
            
            const info = data[code];
            if (info) {
                state.stores[code].lastSync = info.last_sync;
                state.stores[code].isBehind = info.is_behind;
                state.stores[code].daysBehind = info.days_behind;
                state.stores[code].isAhead = info.is_ahead;
                updateCardStatus(code);
            }
        } catch (e) {
            // Ignore individual store failures
        }
    });
    
    await Promise.all(promises);
    
    // Recount totals after all parallel requests finish
    let currentCount = 0;
    let behindCount = 0;
    let aheadCount = 0;
    
    for (const code of Object.keys(state.stores)) {
        const s = state.stores[code];
        if (s.lastSync !== undefined) {
            if (s.isAhead) aheadCount++;
            else if (s.isBehind) behindCount++;
            else currentCount++;
        }
    }
    
    document.getElementById('val-synced').textContent = currentCount;
    document.getElementById('val-behind').textContent = behindCount;
    document.getElementById('val-ahead').textContent = aheadCount;
}

// Update UI
function updateCardStatus(code) {
    const card = document.getElementById(`card-${code}`);
    const dot = document.getElementById(`dot-${code}`);
    const statusText = document.getElementById(`status-text-${code}`);
    const syncTime = document.getElementById(`sync-time-${code}`);
    const syncBtn = document.getElementById(`btn-sync-${code}`);
    
    if (!card || !state.stores[code]) return;
    
    const s = state.stores[code];
    
    // Update Online/Offline
    if (s.online !== undefined) {
        dot.className = `dot ${s.online ? 'online' : 'offline'}`;
        statusText.textContent = s.online ? 'Online' : 'Offline';
        if (!s.online) {
            card.classList.add('status-offline');
            syncBtn.disabled = true;
            syncBtn.innerHTML = 'Offline';
        } else {
            card.classList.remove('status-offline');
        }
    }
    
    // Update Sync Status
    if (s.lastSync !== undefined) {
        card.classList.remove('status-current', 'status-behind-1', 'status-behind-2');
        
        syncTime.textContent = s.lastSync ? s.lastSync : 'Never';
        
        // Update Label
        const labelCount = document.getElementById(`label-count-${code}`);
        if (labelCount) labelCount.innerHTML = `Invoices (${state.date})<br><small style="font-size: 0.75em; color: #a0aec0; letter-spacing: 0.5px; font-weight: 500;">Str_inv / Cen_inv</small>`;
        
        if (!s.isBehind) {
            if (s.isAhead) {
                card.classList.add('status-behind-1'); // Use yellow border for ahead
                syncTime.style.color = 'var(--warning)';
                if (s.online) {
                    syncBtn.disabled = true;
                    syncBtn.innerHTML = 'Ahead Sync';
                }
            } else {
                card.classList.add('status-current');
                syncTime.style.color = 'var(--success)';
                if (s.online) {
                    syncBtn.disabled = true;
                    syncBtn.innerHTML = 'Up to date';
                }
            }
        } else {
            if (s.daysBehind === 1) {
                card.classList.add('status-behind-1');
                syncTime.style.color = 'var(--warning)';
            } else {
                card.classList.add('status-behind-2');
                syncTime.style.color = 'var(--danger)';
            }
            
            if (s.online) {
                syncBtn.disabled = false;
                syncBtn.innerHTML = `
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Sync Now
                    <div class="spinner"></div>
                `;
            }
        }
    }
}

function setFilter(filterType, element) {
    state.currentFilter = filterType;
    
    // Remove active class from all tabs and cards
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.summary-card').forEach(b => b.classList.remove('active-filter'));
    
    if (element) {
        if (element.classList.contains('tab-btn')) {
            element.classList.add('active');
        } else {
            element.classList.add('active-filter');
            // If it's the "Total Stores" card, also light up "All Stores" tab
            if (filterType === 'all') {
                document.querySelector('.tab-btn[data-filter="all"]').classList.add('active');
            }
        }
    }
    
    filterStores();
}

function filterStores() {
    const filter = state.currentFilter;
    const cards = document.querySelectorAll('.store-card');
    const searchInput = document.getElementById('store-search');
    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    
    cards.forEach(card => {
        let show = false;
        const code = card.id.replace('card-', '');
        const storeState = state.stores[code] || {};
        
        // 1. Check Category/Status Filter
        if (filter === 'all') {
            show = true;
        } else if (filter === 'status-online') {
            show = storeState.online === true;
        } else if (filter === 'status-offline') {
            show = storeState.online === false;
        } else if (filter === 'sync-current') {
            show = storeState.isBehind === false && !storeState.isAhead;
        } else if (filter === 'sync-ahead') {
            show = storeState.isAhead === true;
        } else if (filter === 'sync-behind') {
            show = storeState.isBehind === true;
        } else {
            // It's a region filter
            show = card.dataset.region === filter;
        }
        
        // 2. Check Search Filter
        if (show && searchTerm) {
            const cardText = card.textContent.toLowerCase();
            const cardId = code.toLowerCase();
            if (!cardText.includes(searchTerm) && !cardId.includes(searchTerm)) {
                show = false;
            }
        }
        
        card.style.display = show ? 'flex' : 'none';
    });
}

// Actions
function triggerSync(event, code, name) {
    event.stopPropagation(); // Prevent opening modal
    
    const btn = document.getElementById(`btn-sync-${code}`);
    btn.classList.add('syncing');
    btn.disabled = true;
    
    fetch('api/trigger_sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ store_code: code, days: 1 })
    })
    .then(res => res.json())
    .then(data => {
        btn.classList.remove('syncing');
        
        if (data.success) {
            showToast(`Successfully synced ${data.records_synced} records for ${name}`, 'success');
            // Refresh that store's data
            fetchSyncStatus();
            fetchCounts(false);
        } else {
            showToast(`Sync failed: ${data.error}`, 'error');
            btn.disabled = false;
        }
    })
    .catch(err => {
        btn.classList.remove('syncing');
        btn.disabled = false;
        showToast(`Network error during sync`, 'error');
    });
}

function handleCardClick(event, code, name) {
    if (event.target.closest('button')) return; // Ignore sync button clicks
    
    const card = event.currentTarget;
    const rect = card.getBoundingClientRect();
    const x = event.clientX - rect.left;
    
    // Left half opens Central DB (Left Panel), Right half opens Store DB (Right Panel)
    if (x < rect.width / 2) {
        openCentralDetail(code, name);
    } else {
        openStoreDetail(code, name);
    }
}

function openCentralDetail(code, name) {
    const modal = document.getElementById('modal-central-overlay');
    document.getElementById('modal-central-store-name').textContent = `${code} - ${name} (Central Server 17)`;
    modal.classList.add('active');
    
    const tbody = document.getElementById('central-invoice-table-body');
    const totalCount = document.getElementById('central-total-count');
    const notGen = document.getElementById('central-not-gen');
    const testGen = document.getElementById('central-test-gen');
    const handover = document.getElementById('central-handover');
    const missing = document.getElementById('central-missing');
    
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Loading...</td></tr>';
    totalCount.textContent = '...';
    notGen.textContent = '...';
    testGen.textContent = '...';
    handover.textContent = '...';
    missing.textContent = '...';
    
    // Fetch Central Data (ERP)
    fetch(`api/central_detail.php?store_code=${code}&date=${state.date}`)
        .then(res => res.json())
        .then(centralData => {
            if (centralData.error) {
                throw new Error(centralData.error);
            }
            
            const erpCount = centralData.total_count || 0;
            const handoverCount = centralData.handover || 0;
            
            totalCount.textContent = erpCount;
            handover.textContent = handoverCount;
            
            // Now fetch Test Bill metrics from the STORE directly (as a live proxy)
            fetch(`api/store_invoice_count.php?store_code=${code}&date=${state.date}`)
                .then(res => res.json())
                .then(storeData => {
                    const storeInfo = storeData[code] || {};
                    const storeCount = storeInfo.count || 0;
                    
                    testGen.textContent = storeCount;
                    
                    // Calculation logic:
                    // Not Gen = ERP - Store
                    const diffNotGen = erpCount - storeCount;
                    notGen.textContent = diffNotGen > 0 ? diffNotGen : 0;
                    
                    // Missing = Store - Handover
                    const diffMissing = storeCount - handoverCount;
                    missing.textContent = diffMissing > 0 ? diffMissing : 0;
                })
                .catch(() => {
                    testGen.textContent = 'Error';
                    notGen.textContent = 'Error';
                    missing.textContent = 'Error';
                });

            if (centralData.invoices && centralData.invoices.length > 0) {
                tbody.innerHTML = centralData.invoices.map(inv => `
                    <tr>
                        <td>${inv.invno}</td>
                        <td>${parseFloat(inv.amt).toFixed(2)}</td>
                        <td>${inv.invdate}</td>
                        <td>${inv.invtime || inv.entry_time}</td>
                        <td>${inv.tillno}</td>
                        <td>${inv.cashier}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No invoices found for this date on Central.</td></tr>';
            }
        })
        .catch(err => {
            tbody.innerHTML = `<tr><td colspan="6" class="empty-state">Error loading details: ${err.message || 'Unknown error'}</td></tr>`;
            totalCount.textContent = 'Error';
            handover.textContent = 'Error';
        });
}

function openStoreDetail(code, name) {
    const modal = document.getElementById('modal-overlay');
    document.getElementById('modal-store-name').textContent = `${code} - ${name} (${state.date})`;
    modal.classList.add('active');
    
    const tbody = document.getElementById('invoice-table-body');
    const logBody = document.getElementById('log-table-body');
    const testGen = document.getElementById('store-test-gen');
    const handover = document.getElementById('store-handover');
    const alerts = document.getElementById('store-alerts');
    const amt = document.getElementById('store-amt');
    
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Loading...</td></tr>';
    logBody.innerHTML = '<tr><td colspan="4" class="empty-state">Loading...</td></tr>';
    testGen.textContent = '...';
    handover.textContent = '...';
    alerts.textContent = '...';
    amt.textContent = '...';
    
    // Fetch ONLY Store details (Strict separation)
    fetch(`api/store_detail.php?store_code=${code}&date=${state.date}`)
        .then(res => res.json())
        .then(data => {
            const countInv = data.count_invoices || 0;
            const countMan = data.count_manager || 0;
            const countAlerts = data.count_alerts || 0;
            const totalAmt = data.total_amt ? parseFloat(data.total_amt).toFixed(2) : '0.00';
            
            testGen.textContent = countInv;
            handover.textContent = countMan;
            alerts.textContent = countAlerts;
            amt.textContent = totalAmt;
            
            // Render Invoices
            if (data.invoices && data.invoices.length > 0) {
                tbody.innerHTML = data.invoices.map(inv => `
                    <tr>
                        <td>${inv.invno}</td>
                        <td>${parseFloat(inv.amt).toFixed(2)}</td>
                        <td>${inv.invdate}</td>
                        <td>${inv.entry_time}</td>
                        <td>${inv.tillno}</td>
                        <td>${inv.cashier}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No invoices found for this date.</td></tr>';
            }
            
            // Render Logs
            if (data.sync_log && data.sync_log.length > 0) {
                logBody.innerHTML = data.sync_log.map(log => {
                    const statusClass = log.status === 'success' ? 'value-success' : (log.status === 'failed' ? 'value-danger' : 'value-warning');
                    return `
                        <tr>
                            <td>${log.started_at}</td>
                            <td>${log.completed_at || '-'}</td>
                            <td class="${statusClass}">${log.status}</td>
                            <td>${log.records_synced}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                logBody.innerHTML = '<tr><td colspan="4" class="empty-state">No manual sync history.</td></tr>';
            }
        })
        .catch(err => {
            tbody.innerHTML = '<tr><td colspan="6" class="empty-state">Error loading details.</td></tr>';
            logBody.innerHTML = '<tr><td colspan="4" class="empty-state">Error loading history.</td></tr>';
        });
}

function closeModal() {
    document.getElementById('modal-overlay').classList.remove('active');
}

function closeCentralModal() {
    document.getElementById('modal-central-overlay').classList.remove('active');
}

// Toast System
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 
        '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>' : 
        '<svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        
    toast.innerHTML = `
        ${icon}
        <div>${message}</div>
    `;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Remove after 5 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}
