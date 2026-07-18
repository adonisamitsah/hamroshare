<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

$dmat = isset($_GET['dmat']) ? $_GET['dmat'] : null;

if (!$dmat) {
    die("Error: DMAT number is required to view this profile.");
}

// Fetch local data from SQLite
$user = $db->querySingle("SELECT * FROM users WHERE dmat_num = '$dmat'", true);

if (!$user) {
    die("Error: User not found in local database.");
}

// Metadata for the page
$lastSync = !empty($user['last_updated_owndetails']) ? $user['last_updated_owndetails'] : "Never Synced";
$cachedJson = !empty($user['ownDetails']) ? $user['ownDetails'] : "null";
$myDetailsJson = !empty($user['mydetails']) ? $user['mydetails'] : "null";

echo sudo_get_header("User Profile");
?>

<div class="max-w-6xl mx-auto p-4 md:p-10">
    
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                <a href="index.php" class="hover:text-blue-500">Dashboard</a>
                <i class="fas fa-chevron-right text-[8px]"></i>
                <span class="text-slate-300">Investor Profile</span>
            </nav>
            <h1 class="text-3xl font-black text-white tracking-tight">Profile Management</h1>
        </div>

        <div class="flex items-center gap-4 bg-slate-900/50 p-2 pr-4 rounded-2xl border border-slate-800">
            <button onclick="syncMeroShareDetails('<?php echo $dmat; ?>')" id="refresh-btn" 
                    class="bg-blue-600 hover:bg-blue-500 text-white w-10 h-10 rounded-xl transition-all flex items-center justify-center shadow-lg shadow-blue-600/20">
                <i class="fas fa-sync-alt" id="sync-icon"></i>
            </button>
            <div>
                <p class="text-[9px] text-slate-500 uppercase font-bold leading-none mb-1">Last MeroShare Sync</p>
                <p id="sync-time" class="text-xs font-mono text-slate-300"><?php echo $lastSync; ?></p>
            </div>
        </div>
    </div>

    <div id="profile-viewport" class="relative min-h-[400px]">
        
        <div id="dossier-loader" class="hidden absolute inset-0 z-50 flex flex-col items-center justify-center bg-[#0b0e14]/90 backdrop-blur-sm rounded-3xl">
            <div class="w-12 h-12 border-4 border-blue-500/10 border-t-blue-500 rounded-full animate-spin"></div>
            <p class="mt-4 text-blue-500 text-[10px] font-black uppercase tracking-widest">Updating from MeroShare...</p>
        </div>

        <div id="dossier-container" class="transition-all duration-500">
            <?php if($cachedJson == "null"): ?>
                <div class="bg-slate-900/30 border-2 border-dashed border-slate-800 rounded-3xl p-20 text-center">
                    <i class="fas fa-cloud-download-alt text-4xl text-slate-700 mb-4"></i>
                    <p class="text-slate-500 text-sm">No local data found for this user.</p>
                    <button onclick="syncMeroShareDetails('<?php echo $dmat; ?>')" class="mt-4 text-blue-500 text-xs font-bold uppercase underline">Fetch Now</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
// 1. Global Parameters - Define these first to avoid ReferenceErrors
const userConfig = {
    clientId: '<?php echo $user['clientId']; ?>',
    username: '<?php echo $user['username']; ?>',
    password: '<?php echo $user['password']; ?>',
    name: '<?php echo $user['name']; ?>',
    dmat: '<?php echo $dmat; ?>'
};
const dmatNum = userConfig.dmat;
const initialData = <?php echo $cachedJson; ?>;
const myDetailsData = <?php echo $myDetailsJson; ?>;

// 3. Document Ready (For UI initialization only)
$(document).ready(function() {
    if (initialData) {
        renderDossier(initialData);
    } else {
        syncMeroShareDetails(userConfig.dmat);
    }
});

/**
 * Renders the MeroShare ownDetail JSON into the UI
 */
function renderDossier(data) {
    // Get the password from our userConfig (injected from PHP)
    const currentPassword = userConfig.password;
    
    // Safely extract mydetails data
    const md = myDetailsData || {};

    // Calculate Age & Minor Warning
    let ageWarningHtml = '';
    let currentAge = '---';
    if (md.dob) {
        const dob = new Date(md.dob);
        const today = new Date();
        
        // Calculate basic age
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        currentAge = age;

        // Exact Minor-to-Adult Calculation
        const turning18Date = new Date(dob.getFullYear() + 18, dob.getMonth(), dob.getDate());
        const daysUntil18 = Math.ceil((turning18Date.getTime() - today.getTime()) / (1000 * 3600 * 24));

        if (daysUntil18 > 0 && daysUntil18 <= 180) {
            // Turning 18 within 6 months
            ageWarningHtml = `
                <div class="lg:col-span-3 bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 flex items-start gap-4 shadow-lg shadow-amber-500/5">
                    <div class="mt-1 text-amber-500"><i class="fas fa-exclamation-triangle text-xl"></i></div>
                    <div>
                        <h4 class="text-amber-400 font-bold text-sm uppercase tracking-widest mb-1">Minor to Adult Transition Pending</h4>
                        <p class="text-amber-500/80 text-xs">This user is turning 18 in <span class="font-bold text-amber-400">${daysUntil18} days</span> (${turning18Date.toISOString().split('T')[0]}). Demat and NEPSE TMS operations might be suspended soon until minor-to-adult KYC updates and a citizenship certificate are provided to the Capital.</p>
                    </div>
                </div>
            `;
        } else if (daysUntil18 <= 0 && daysUntil18 >= -90) {
            // Turned 18 recently (within last 90 days)
            ageWarningHtml = `
                <div class="lg:col-span-3 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 flex items-start gap-4 shadow-lg shadow-rose-500/5">
                    <div class="mt-1 text-rose-500"><i class="fas fa-shield-alt text-xl"></i></div>
                    <div>
                        <h4 class="text-rose-400 font-bold text-sm uppercase tracking-widest mb-1">Action Required: Minor Status Expired</h4>
                        <p class="text-rose-500/80 text-xs">This user turned 18 <span class="font-bold text-rose-400">${Math.abs(daysUntil18)} days ago</span>. If their Demat or TMS is inactive, they must submit an Adult KYC form and citizenship details to their Depository Participant.</p>
                    </div>
                </div>
            `;
        }
    }

    const html = `
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
            
            ${ageWarningHtml}

            <div class="lg:col-span-1 space-y-6">
                <div class="bg-[#161b22] border border-slate-800 rounded-3xl p-6 text-center shadow-xl">
                    <div class="w-24 h-24 bg-slate-800 rounded-full mx-auto mb-4 flex items-center justify-center text-slate-600 text-4xl border-4 border-slate-900 shadow-inner">
                        <i class="fas fa-user"></i>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-1 uppercase">${data.name}</h2>
                    <p class="text-blue-500 font-mono text-xs tracking-tighter mb-4">${data.demat}</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <span class="bg-emerald-500/10 text-emerald-500 text-[9px] font-black px-2 py-1 rounded border border-emerald-500/20 uppercase tracking-widest">Active</span>
                        <span class="bg-slate-800 text-slate-400 text-[9px] font-black px-2 py-1 rounded border border-slate-700 uppercase tracking-widest">${data.customerTypeCode}</span>
                        ${currentAge < 18 ? `<span class="bg-amber-500/10 text-amber-500 text-[9px] font-black px-2 py-1 rounded border border-amber-500/20 uppercase tracking-widest">MINOR</span>` : ''}
                    </div>
                </div>

                <div class="bg-[#161b22] border border-slate-800 rounded-3xl p-6 shadow-xl">
                    <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Account Expiry</h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">MeroShare</span>
                            <span class="text-xs font-mono font-bold text-rose-500">${data.expiredDateStr}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs text-slate-400">Demat</span>
                            <span class="text-xs font-mono font-bold text-blue-500">${data.dematExpiryDate}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 space-y-8">
                <div class="bg-[#161b22] border border-slate-800 rounded-3xl overflow-hidden shadow-xl">
                    <div class="px-8 py-5 border-b border-slate-800 bg-slate-800/30 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-white">Identity Details</h3>
                        <span class="text-[10px] text-slate-500 font-mono">BOID: ${data.boid}</span>
                    </div>
                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-y-8 gap-x-12">
                        <div>
                            <label class="text-[10px] text-slate-600 uppercase font-black block mb-1">Date of Birth / Age</label>
                            <p class="text-slate-200 font-mono">${md.dob || '---'} <span class="text-slate-500 text-xs ml-1">(${currentAge} yrs)</span></p>
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-600 uppercase font-black block mb-1">Contact Number</label>
                            <p class="text-slate-200 font-mono">${data.contact}</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] text-slate-600 uppercase font-black block mb-1">Permanent Address</label>
                            <p class="text-slate-300 leading-relaxed">${md.address || data.address}</p>
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-600 uppercase font-black block mb-1">Gender / Type</label>
                            <p class="text-slate-200 uppercase text-xs tracking-widest">${data.gender == 'M' ? 'Male' : 'Female'} Investor</p>
                        </div>
                        <div>
                            <label class="text-[10px] text-slate-600 uppercase font-black block mb-1">Resident Status</label>
                            <p class="text-slate-200 font-mono text-xs">${md.subStatus || '---'}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-slate-900/30 border border-slate-800 rounded-2xl p-6 shadow-xl">
                        <h4 class="text-[10px] text-slate-500 uppercase font-black mb-4 flex items-center gap-2">
                            <i class="fas fa-history"></i> Registration Dates
                        </h4>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-xs text-slate-500">Member Since</span>
                                <span class="text-xs text-slate-200 font-mono">${data.createdApproveDateStr}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-xs text-slate-500">Last Renewed</span>
                                <span class="text-xs text-slate-200 font-mono">${data.renewedDateStr}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900/30 border border-slate-800 rounded-2xl p-6 shadow-xl">
                        <div class="flex justify-between items-start mb-4">
                            <h4 class="text-[10px] text-slate-500 uppercase font-black flex items-center gap-2">
                                <i class="fas fa-key"></i> Credentials
                            </h4>
                            <button onclick="initPasswordChange('${userConfig.dmat},${userConfig.name}')" class="text-[9px] bg-blue-600/10 hover:bg-blue-600/20 text-blue-500 font-black px-2 py-1 rounded border border-blue-500/20 uppercase transition-colors">
                                <i class="fas fa-edit mr-1"></i> Change
                            </button>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="bg-slate-950/50 rounded-xl p-3 border border-slate-800 flex justify-between items-center">
                                <div class="flex flex-col">
                                    <span class="text-[8px] text-slate-600 uppercase font-bold">Stored Password</span>
                                    <input type="password" id="pass-field" value="${currentPassword}" readonly 
                                        class="bg-transparent text-xs text-blue-400 font-mono focus:outline-none w-32 border-none p-0">
                                </div>
                                <button onclick="togglePassVisibility()" class="text-slate-600 hover:text-slate-400 px-2 transition-colors">
                                    <i class="fas fa-eye" id="eye-icon"></i>
                                </button>
                            </div>

                            <div class="space-y-2 pt-2 border-t border-slate-800/50">
                                <div class="flex justify-between">
                                    <span class="text-[10px] text-slate-500">Last Changed</span>
                                    <span class="text-[10px] text-slate-200 font-mono">${data.passwordChangedDateStr}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-[10px] text-amber-500 font-bold">Policy Expiry</span>
                                    <span class="text-[10px] text-amber-500 font-mono font-bold">${data.passwordExpiryDateStr}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/5 rounded-full blur-2xl -mr-10 -mt-10 pointer-events-none"></div>
                        <h4 class="text-[10px] text-slate-500 uppercase font-black mb-5 flex items-center gap-2">
                            <i class="fas fa-university"></i> Financial Ledger
                        </h4>
                        <div class="space-y-4 relative z-10">
                            <div>
                                <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Bank Name</label>
                                <p class="text-slate-200 text-xs font-semibold">${md.bankName || 'Not Synced'}</p>
                            </div>
                            <div class="flex justify-between gap-4">
                                <div>
                                    <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Account Number</label>
                                    <p class="text-blue-400 font-mono text-sm font-bold tracking-wider">${md.accountNumber || '---'}</p>
                                </div>
                                <div class="text-right">
                                    <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Type / Status</label>
                                    <span class="block text-[9px] text-slate-400 font-mono mb-1">${md.accountType || '---'}</span>
                                    <span class="inline-block text-[9px] bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded font-black uppercase tracking-widest">${md.accountStatusName || 'UNKNOWN'}</span>
                                </div>
                            </div>
                            <div class="pt-3 border-t border-slate-800/60 grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Bank/Branch Code</label>
                                    <p class="text-slate-400 font-mono text-[10px]">${md.bankCode || '--'} / ${md.branchCode || '--'}</p>
                                </div>
                                <div class="text-right">
                                    <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Depository (DP)</label>
                                    <p class="text-slate-400 text-[9px] uppercase">${md.dpName || 'Not Available'}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-rose-500/5 rounded-full blur-2xl -mr-10 -mt-10 pointer-events-none"></div>
                        <h4 class="text-[10px] text-slate-500 uppercase font-black mb-5 flex items-center gap-2">
                            <i class="fas fa-users"></i> KYC & Family
                        </h4>
                        <div class="space-y-4 relative z-10">
                            <div>
                                <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Father / Mother Name</label>
                                <p class="text-slate-200 text-xs uppercase font-semibold">${md.fatherMotherName || '---'}</p>
                            </div>
                            <div>
                                <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Grandfather / Spouse Name</label>
                                <p class="text-slate-200 text-xs uppercase font-semibold">${md.grandfatherSpouseName || '---'}</p>
                            </div>
                            <div class="flex justify-between gap-4 pt-3 border-t border-slate-800/60">
                                <div>
                                    <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Citizenship No.</label>
                                    <p class="text-slate-300 font-mono text-[11px]">${md.citizenshipNumber || '---'}</p>
                                </div>
                                <div class="text-right">
                                    <label class="text-[9px] text-slate-600 uppercase font-bold block mb-0.5">Issued From</label>
                                    <p class="text-slate-300 uppercase text-[10px]">${md.issuedFrom || '---'} <br/> ${md.issuedDate ? '('+md.issuedDate+')' : ''}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    `;
    $('#dossier-container').html(html);
}

/**
 * Calls the Sync API and handles errors via Modals
 */
function syncMeroShareDetails(dmat) {
    $('#dossier-loader').fadeIn(200);
    $('#sync-icon').addClass('fa-spin');
    $('#refresh-btn').prop('disabled', true);

    $.ajax({
        url: `json-api.php?action=fetch_and_save_details&dmat=${dmat}`,
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.status === "success") {
                renderDossier(response.data);
                $('#sync-time').text(response.last_sync);
            } else if (response.status === "unauthorized") {
                showSentinelModal(
                    "Session Expired", 
                    "MeroShare Authorization is no longer valid. Please perform a re-login from the main dashboard.", 
                    "warning"
                );
            } else {
                showSentinelModal(
                    "Sync Failed", 
                    "Data retrieval error: " + response.message, 
                    "error"
                );
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            showSentinelModal(
                "System Error", 
                "Unable to reach the internal API Gateway. Status: " + textStatus, 
                "error"
            );
        },
        complete: function() {
            $('#dossier-loader').fadeOut(300);
            $('#sync-icon').removeClass('fa-spin');
            $('#refresh-btn').prop('disabled', false);
        }
    });
}
</script>