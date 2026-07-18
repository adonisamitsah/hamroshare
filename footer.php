</div> <!-- Closes the p-8 / Content div -->
    </main> <!-- Closes the #main tag -->
</div> <!-- Closes the #layout tag -->

<!-- Core Dependencies -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>


<!-- Specialized Tools -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-modal/0.9.1/jquery.modal.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<!-- Include jsPDF Library right above your script execution matrices -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<!-- App Scripts (Ensure this is linked) -->
<script src="assets/js/app_logic.js"></script> 

<script type="text/javascript">
    // Technical Tooltip Replacement (Simple & Lightweight)
    $(document).ready(function() {
        // You can add global table search logic here too
        $("#table_search").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("table tbody tr:not(.nosearch)").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });

    //master admin logout.

function showConfirmationModal(title, message, confirmCallback) {
    // 1. Check for/Inject the Modal HTML
    if ($('#sentinelModal').length === 0) {
        $('body').append(`
            <div id="sentinelModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(2, 6, 23, 0.85); backdrop-filter:blur(8px); align-items:center; justify-content:center; padding:1rem; font-family: 'Inter', sans-serif;">
                <div style="background:#161b22; border:1px solid #30363d; width:100%; max-width:400px; border-radius:1.5rem; overflow:hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);">
                    <div style="padding:2rem; text-align:center;">
                        <h3 id="modalTitle" style="color:#ffffff; font-weight:700; font-size:1.25rem; margin-bottom:0.5rem;"></h3>
                        <p id="modalMessage" style="color:#8b949e; font-size:0.875rem; line-height:1.5;"></p>
                    </div>
                    <div id="modalFooter" style="padding:1rem; background:rgba(48, 54, 61, 0.2); display:flex; justify-content:flex-end; gap:0.75rem; border-top:1px solid #30363d;">
                        <!-- Buttons injected dynamically -->
                    </div>
                </div>
            </div>
        `);
    }

    // 2. Set Content
    $('#modalTitle').text(title);
    $('#modalMessage').html(message);
    
    // 3. Define the Close Logic
    const closeModal = () => $('#sentinelModal').css('display', 'none');

    // 4. Build and Bind Buttons
    $('#modalFooter').empty().html(`
        <button id="cancelBtn" style="background:transparent; border:none; color:#8b949e; font-size:0.75rem; font-weight:800; cursor:pointer; padding:0.5rem 1rem; text-transform:uppercase; letter-spacing:0.05em;">
            Cancel
        </button>
        <button id="confirmBtn" style="background:#e11d48; color:#ffffff; border:none; border-radius:0.75rem; font-size:0.75rem; font-weight:800; cursor:pointer; padding:0.6rem 1.5rem; text-transform:uppercase; letter-spacing:0.05em; box-shadow: 0 10px 15px -3px rgba(225, 29, 72, 0.2);">
            Yes, Proceed
        </button>
    `);

    // 5. Attach Listeners
    $('#cancelBtn').on('click', closeModal);
    
    $('#confirmBtn').off('click').one('click', function() {
        if (typeof confirmCallback === 'function') confirmCallback();
        closeModal();
    });

    // 6. Show the UI
    $('#sentinelModal').css('display', 'flex');
}

$(document).on("click", "#master-logout-btn", function(e) {
    e.preventDefault();
    showConfirmationModal(
        "Secure Logout", 
        "Are you sure you want to end your master session and logout?", 
        function() { window.location.href = "logout.php"; }
    );
});

document.addEventListener('DOMContentLoaded', function() {
    // 1. Live Search Filter
    const searchInput = document.getElementById('matrix_search');
    if(searchInput) {
        const cards = document.querySelectorAll('.matrix-card');
        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            cards.forEach(card => {
                const scripName = card.getAttribute('data-scrip');
                if (scripName.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // 2. Initialize God-Level Sparkline Charts
    Chart.defaults.color = '#64748b';
    Chart.defaults.font.family = 'monospace';

    document.querySelectorAll('.signal-chart').forEach(canvas => {
        const ctx = canvas.getContext('2d');
        const themeColor = canvas.dataset.color; // #10b981, #f43f5e, etc.
        const prices = JSON.parse(canvas.dataset.prices);
        const dates = JSON.parse(canvas.dataset.dates);

        // Create a beautiful fade gradient beneath the line
        let gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentElement.clientHeight);
        gradient.addColorStop(0, themeColor + '50'); // 50% opacity
        gradient.addColorStop(1, themeColor + '00'); // 0% opacity

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    data: prices,
                    borderColor: themeColor,
                    backgroundColor: gradient,
                    borderWidth: 2,
                    pointRadius: 0, // Hide points for a clean sparkline look
                    pointHoverRadius: 4,
                    fill: true,
                    tension: 0.4 // Smooth curved lines
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#0f172a',
                        titleColor: '#94a3b8',
                        bodyColor: '#f8fafc',
                        borderColor: '#334155',
                        borderWidth: 1,
                        displayColors: false,
                        callbacks: {
                            label: function(context) { return 'Rs. ' + context.parsed.y; }
                        }
                    }
                },
                scales: {
                    x: { display: false }, // Hide X axis completely
                    y: { 
                        display: false, // Hide Y axis
                        min: Math.min(...prices) * 0.98, // Give slight padding below
                        max: Math.max(...prices) * 1.02  // Give slight padding above
                    } 
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        });
    });
});
</script>

</body>
</html>


