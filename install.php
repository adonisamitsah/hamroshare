<?php
// install.php
// Hamroshare Automated Database Installer (Dynamic Schema Version)

$dbFile = 'data.sqlite'; // Matches your .env default
$dbPath = __DIR__ . '/' . $dbFile;
$schemaPath = __DIR__ . '/schema.sql'; // Path to your schema file

// Security Check: If DB already exists, block access to prevent overwriting
if (file_exists($dbPath)) {
    die("<h2 style='color:red; text-align:center; font-family:sans-serif; margin-top:50px;'>Installation already completed. <br><small>Please delete install.php for security.</small></h2>");
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminName  = trim($_POST['admin_name'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = trim($_POST['password'] ?? '');

    if (empty($adminName) || empty($adminEmail) || empty($adminPass)) {
        $message = "<div class='bg-red-500/20 border border-red-500 text-red-400 p-3 rounded mb-4'>All fields are required.</div>";
    } else {
        try {
            // 0. Check if schema.sql exists before we create a blank database
            if (!file_exists($schemaPath)) {
                throw new Exception("<b>schema.sql</b> file is missing. Please make sure it is in the root directory.");
            }

            $schema = file_get_contents($schemaPath);
            if (empty(trim($schema))) {
                throw new Exception("<b>schema.sql</b> is empty. Please add your database structure to it.");
            }

            // 1. Create the SQLite Database
            $db = new SQLite3($dbPath);
            $db->enableExceptions(true);

            // 2. Execute the Database Schema dynamically from the file
            $db->exec($schema);

            // 3. Inject Constants (Using dynamically submitted credentials)
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT);

            $constants = [
                ['master_password', $hashedPassword],
                ['admin_email', $adminEmail],
                ['admin_name', $adminName],
                ['appliedKitta', '10'],
                ['capital_as_options', '<option value="1287xxxxxAakash Capital Limited">Aakash Capital Limited(19000)</option><option value="1315xxxxxAakashbhairab Securities Limited">Aakashbhairab Securities Limited(20600)</option><option value="128xxxxxAbc Securities Private Limited">Abc Securities Private Limited(13200)</option><option value="129xxxxxAgrawal Securities Private Limited">Agrawal Securities Private Limited(12300)</option><option value="130xxxxxAgricultural Development Bank Limited">Agricultural Development Bank Limited(17200)</option><option value="2155xxxxxApple Securities Pvt. Ltd.">Apple Securities Pvt. Ltd.(22300)</option><option value="2136xxxxxArun Securities Pvt. Ltd.">Arun Securities Pvt. Ltd.(21800)</option><option value="131xxxxxAryatara Investment And Securities Private Limited">Aryatara Investment And Securities Private Limited(11900)</option><option value="201xxxxxAsian Capital Limited">Asian Capital Limited(17500)</option><option value="133xxxxxAsian Securities Private Limited">Asian Securities Private Limited(14700)</option><option value="2170xxxxxBeni Securities Pvt. Ltd.">Beni Securities Pvt. Ltd.(23200)</option><option value="1298xxxxxBhole Ganesh Securities Limited.">Bhole Ganesh Securities Limited.(19100)</option><option value="135xxxxxBhrikuti Stock Broking Company Private Limited">Bhrikuti Stock Broking Company Private Limited(15000)</option><option value="1314xxxxxBlue Chip Securities Limited">Blue Chip Securities Limited(20700)</option><option value="132xxxxxBrilliant Securities Pvt. Ltd.">Brilliant Securities Pvt. Ltd.(15600)</option><option value="1318xxxxxCapital Hub Pvt. Ltd.">Capital Hub Pvt. Ltd.(20900)</option><option value="1292xxxxxCapital Max Securities Limited">Capital Max Securities Limited(19500)</option><option value="137xxxxxCitizens Bank International Limited">Citizens Bank International Limited(11700)</option><option value="139xxxxxCreative Securities Private Limited">Creative Securities Private Limited(13300)</option><option value="140xxxxxCrystal Kanchanjungha Securities Pvt. Ltd">Crystal Kanchanjungha Securities Pvt. Ltd(13400)</option><option value="141xxxxxDakshinkali Investment And Securities Private Limited">Dakshinkali Investment And Securities Private Limited(12000)</option><option value="142xxxxxDeevyaa Securities & Stock House Private Limited">Deevyaa Securities & Stock House Private Limited(14500)</option><option value="143xxxxxDipshikha Dhitopatra Karobar Company (p.) Ltd.">Dipshikha Dhitopatra Karobar Company (p.) Ltd.(11300)</option><option value="144xxxxxDynamic Money Managers Securities Private Limited">Dynamic Money Managers Securities Private Limited(14900)</option><option value="1311xxxxxElite Merchant Capital Limited">Elite Merchant Capital Limited(20300)</option><option value="1305xxxxxElite Stock House Limited">Elite Stock House Limited(19800)</option><option value="145xxxxxEverest Bank Ltd.">Everest Bank Ltd.(10800)</option><option value="153xxxxxGarima Capital Limited">Garima Capital Limited(17600)</option><option value="2137xxxxxGarima Securities Limited">Garima Securities Limited(21900)</option><option value="134xxxxxGlobal Ime Bank Limited">Global Ime Bank Limited(11100)</option><option value="151xxxxxGlobal Ime Bank Limited">Global Ime Bank Limited(12200)</option><option value="146xxxxxGlobal Ime Capital Limited">Global Ime Capital Limited(11200)</option><option value="147xxxxxGuheswori Merchant Banking & Finance Limited">Guheswori Merchant Banking & Finance Limited(16200)</option><option value="681xxxxxGurkhas Finance Limited">Gurkhas Finance Limited(18000)</option><option value="1317xxxxxHatemalo Financial Services Private Limited">Hatemalo Financial Services Private Limited(20500)</option><option value="2164xxxxxHimalaya Securities Banker Limited">Himalaya Securities Banker Limited(22900)</option><option value="1297xxxxxHimalayan Brokerage Company Limited">Himalayan Brokerage Company Limited(19600)</option><option value="138xxxxxHimalayan Capital Limited">Himalayan Capital Limited(10100)</option><option value="148xxxxxHimalayan Capital Limited">Himalayan Capital Limited(17700)</option><option value="2162xxxxxHimalayan Investment Banker Limited">Himalayan Investment Banker Limited(22800)</option><option value="149xxxxxIcfc Finance Limited">Icfc Finance Limited(17400)</option><option value="150xxxxxImperial Securities Company Limited">Imperial Securities Company Limited(13100)</option><option value="1308xxxxxIndex Securities Limited">Index Securities Limited(20000)</option><option value="1316xxxxxIndira Securities Pvt. Ltd.">Indira Securities Pvt. Ltd.(20800)</option><option value="1306xxxxxInfinity Securities Limited">Infinity Securities Limited(19900)</option><option value="2167xxxxxInvestment Management Nepal Pvt. Ltd.">Investment Management Nepal Pvt. Ltd.(23100)</option><option value="2169xxxxxJf Securities Company Pvt. Ltd.">Jf Securities Company Pvt. Ltd.(23300)</option><option value="402xxxxxJyoti Bikash Bank Limited">Jyoti Bikash Bank Limited(17900)</option><option value="2140xxxxxK.b.l. Securities Limited.">K.b.l. Securities Limited.(22000)</option><option value="1309xxxxxKalash Stock Market Pvt. Ltd.">Kalash Stock Market Pvt. Ltd.(20100)</option><option value="1271xxxxxKalika Securities Pvt. Ltd.">Kalika Securities Pvt. Ltd.(18700)</option><option value="1182xxxxxKamana Sewa Bikas Bank Limited.">Kamana Sewa Bikas Bank Limited.(18200)</option><option value="154xxxxxKohinoor Investment & Securities Private Limited">Kohinoor Investment & Securities Private Limited(14300)</option><option value="156xxxxxKumari Bank Limited">Kumari Bank Limited(15200)</option><option value="168xxxxxKumari Bank Limited">Kumari Bank Limited(16300)</option><option value="195xxxxxLaxmi Sunrise Capital Limited">Laxmi Sunrise Capital Limited(12400)</option><option value="157xxxxxLaxmi Sunrise Capital Limited">Laxmi Sunrise Capital Limited(10700)</option><option value="158xxxxxLinch Stock Market Limited">Linch Stock Market Limited(13800)</option><option value="159xxxxxMachhapuchchhre Bank Limited">Machhapuchchhre Bank Limited(16100)</option><option value="155xxxxxMachhapuchchhre Capital Limited">Machhapuchchhre Capital Limited(14100)</option><option value="1327xxxxxMachhapuchchhre Securities Ltd.">Machhapuchchhre Securities Ltd.(21400)</option><option value="2156xxxxxMagnet Securities And Investment Company Pvt. Ltd.">Magnet Securities And Investment Company Pvt. Ltd.(22200)</option><option value="160xxxxxMahalaxmi Bikas Bank Limited">Mahalaxmi Bikas Bank Limited(16700)</option><option value="1281xxxxxManjushree Finance Limited">Manjushree Finance Limited(18900)</option><option value="161xxxxxMarket Securities Exchange Company Pvt. Ltd">Market Securities Exchange Company Pvt. Ltd(13600)</option><option value="1329xxxxxMilky Way Share Broker Company Ltd.">Milky Way Share Broker Company Ltd.(21600)</option><option value="1295xxxxxMiyo Securities Private Limited">Miyo Securities Private Limited(19700)</option><option value="1325xxxxxMoney World Share Exchange Pvt. Ltd.">Money World Share Exchange Pvt. Ltd.(21100)</option><option value="199xxxxxMuktinath Capital Limited">Muktinath Capital Limited(12500)</option><option value="163xxxxxNaasa Securities Company Ltd">Naasa Securities Company Ltd(15900)</option><option value="198xxxxxNabil Bank Limited">Nabil Bank Limited(16800)</option><option value="166xxxxxNabil Bank Limited">Nabil Bank Limited(15100)</option><option value="164xxxxxNabil Investment Banking Ltd.">Nabil Investment Banking Ltd.(10400)</option><option value="1320xxxxxNagarik Stock Dealer Company Limited">Nagarik Stock Dealer Company Limited(20400)</option><option value="2171xxxxxNational Capital Limited">National Capital Limited(23400)</option><option value="167xxxxxNepal Bank Limited">Nepal Bank Limited(15700)</option><option value="169xxxxxNepal Dp Limited">Nepal Dp Limited(15500)</option><option value="2182xxxxxNepal Investment And Securities Trading Pvt. Ltd.">Nepal Investment And Securities Trading Pvt. Ltd.(23500)</option><option value="165xxxxxNepal Life Capital Limited">Nepal Life Capital Limited(16400)</option><option value="170xxxxxNepal Sbi Bank Limited">Nepal Sbi Bank Limited(15300)</option><option value="171xxxxxNepal Stock House Private Limited">Nepal Stock House Private Limited(11500)</option><option value="174xxxxxNic Asia Bank Limited">Nic Asia Bank Limited(13700)</option><option value="173xxxxxNimb Ace Capital Limited">Nimb Ace Capital Limited(10600)</option><option value="172xxxxxNimb Ace Capital Limited">Nimb Ace Capital Limited(10200)</option><option value="162xxxxxNimb Ace Capital Limited">Nimb Ace Capital Limited(17300)</option><option value="175xxxxxNmb Capital Limited">Nmb Capital Limited(11000)</option><option value="176xxxxxOnline Securities Limited">Online Securities Limited(11800)</option><option value="1324xxxxxOpal Securities Investment Pvt. Ltd.">Opal Securities Investment Pvt. Ltd.(21200)</option><option value="177xxxxxOxford Securities Pvt. Ltd.">Oxford Securities Pvt. Ltd.(17000)</option><option value="1328xxxxxPahi Investment Pvt. Ltd.">Pahi Investment Pvt. Ltd.(21300)</option><option value="178xxxxxPrabhu Bank Limited">Prabhu Bank Limited(13900)</option><option value="136xxxxxPrabhu Bank Limited">Prabhu Bank Limited(16000)</option><option value="179xxxxxPrabhu Capital Limited">Prabhu Capital Limited(12600)</option><option value="2161xxxxxPragyan Securities Pvt. Ltd.">Pragyan Securities Pvt. Ltd.(22600)</option><option value="180xxxxxPremier Securities Company Limited">Premier Securities Company Limited(14800)</option><option value="152xxxxxPrime Commercial Bank Limited">Prime Commercial Bank Limited(15400)</option><option value="181xxxxxPrime Commercial Bank Limited">Prime Commercial Bank Limited(16900)</option><option value="182xxxxxPrimo Securities Private Limited">Primo Securities Private Limited(12800)</option><option value="1270xxxxxProgressive Finance Limited">Progressive Finance Limited(18600)</option><option value="1293xxxxxProperty Wizard Limited">Property Wizard Limited(19400)</option><option value="183xxxxxProvident Merchant Banking Limited">Provident Merchant Banking Limited(16600)</option><option value="2165xxxxxR.b.b. Securities Company Ltd.">R.b.b. Securities Company Ltd.(23000)</option><option value="184xxxxxRbb Merchant Banking Limited">Rbb Merchant Banking Limited(16500)</option><option value="2142xxxxxRoadshow Securities Ltd.">Roadshow Securities Ltd.(22100)</option><option value="1326xxxxxS.p.s.a. Securities Ltd.">S.p.s.a. Securities Ltd.(21500)</option><option value="2134xxxxxSajilo Broker Limited">Sajilo Broker Limited(21700)</option><option value="1080xxxxxSampanna Capital And Advisory Nepal Limited">Sampanna Capital And Advisory Nepal Limited(18100)</option><option value="185xxxxxSani Securities Company Limited">Sani Securities Company Limited(14400)</option><option value="186xxxxxSanima Bank Ltd">Sanima Bank Ltd(15800)</option><option value="2157xxxxxSanima Securities Limited">Sanima Securities Limited(22400)</option><option value="187xxxxxSecured Securities Limited">Secured Securities Limited(11600)</option><option value="188xxxxxSewa Securities Private Limited">Sewa Securities Private Limited(12700)</option><option value="1189xxxxxShangri-la Development Bank Limited">Shangri-la Development Bank Limited(18400)</option><option value="1294xxxxxSharepro Securities Pvt.ltd.">Sharepro Securities Pvt.ltd.(19200)</option><option value="1196xxxxxShine Resunga Development Bank Limited">Shine Resunga Development Bank Limited(18500)</option><option value="1274xxxxxShree Investment And Finance Co. Ltd.">Shree Investment And Finance Co. Ltd.(18800)</option><option value="189xxxxxShree Krishna Securities Limited">Shree Krishna Securities Limited(12900)</option><option value="1310xxxxxShubhakamana Securities Pvt. Ltd.">Shubhakamana Securities Pvt. Ltd.(20200)</option><option value="190xxxxxSiddhartha Capital Limited">Siddhartha Capital Limited(10900)</option><option value="191xxxxxSipla Securities Private Limited">Sipla Securities Private Limited(14600)</option><option value="192xxxxxSouth Asian Bulls Private Limited">South Asian Bulls Private Limited(13000)</option><option value="193xxxxxSri Hari Securities Pvt. Ltd.">Sri Hari Securities Pvt. Ltd.(14000)</option><option value="1319xxxxxStoxkarts Securities Limited">Stoxkarts Securities Limited(21000)</option><option value="194xxxxxSumeru Securities Private Limited">Sumeru Securities Private Limited(14200)</option><option value="1296xxxxxSun Securities Pvt. Ltd.">Sun Securities Pvt. Ltd.(19300)</option><option value="370xxxxxSundhara Securities Limited">Sundhara Securities Limited(17800)</option><option value="2158xxxxxSunlife Capital Limited">Sunlife Capital Limited(22500)</option><option value="1186xxxxxSwarnalaxmi Securities Pvt. Ltd.">Swarnalaxmi Securities Pvt. Ltd.(18300)</option><option value="2163xxxxxTrademow Securities Pvt. Ltd.">Trademow Securities Pvt. Ltd.(22700)</option><option value="196xxxxxTrishakti Securities Limited">Trishakti Securities Limited(11400)</option><option value="197xxxxxTrishul Securities & Investment Limited">Trishul Securities & Investment Limited(17100)</option><option value="200xxxxxVision Securities Pvt. Ltd">Vision Securities Pvt. Ltd(13500)</option>']
            ];

            // Use prepared statements to safely handle HTML tags and special characters
            $stmtConst = $db->prepare("INSERT INTO constant (`key`, `value`) VALUES (:k, :v)");
            foreach ($constants as $c) {
                $stmtConst->bindValue(':k', $c[0], SQLITE3_TEXT);
                $stmtConst->bindValue(':v', $c[1], SQLITE3_TEXT);
                $stmtConst->execute();
            }

            $message = "<div class='bg-emerald-500/20 border border-emerald-500 text-emerald-400 p-4 rounded mb-4 text-center'>
                            <b>✅ Installation Successful!</b><br>
                            Database created and Master Constants configured.<br>
                            <span class='text-sm mt-2 block text-gray-400'>Please delete <b>install.php</b> before deploying to production.</span>
                            <a href='index.php' class='mt-4 inline-block bg-indigo-600 text-white px-4 py-2 rounded font-bold hover:bg-indigo-500'>Go to Login</a>
                        </div>";
            
            $hideForm = true;

        } catch (Exception $e) {
            if (file_exists($dbPath)) unlink($dbPath); // Cleanup corrupted file
            $message = "<div class='bg-red-500/20 border border-red-500 text-red-400 p-3 rounded mb-4'><b>Database Error:</b> " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Hamroshare Installer</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-950 text-gray-200 min-h-screen flex items-center justify-center font-sans p-4">
    <div class="bg-gray-900 border border-gray-800 p-8 rounded-2xl max-w-md w-full shadow-2xl">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-indigo-500/10 border border-indigo-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
            </div>
            <h1 class="text-2xl font-bold text-white">System Setup</h1>
            <p class="text-gray-400 text-sm mt-1">Initialize the Hamroshare database</p>
        </div>

        <?= $message ?>

        <?php if (!isset($hideForm)): ?>
        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-bold mb-1">Admin Name</label>
                <input type="text" name="admin_name" required placeholder="John Doe" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-bold mb-1">Admin Email</label>
                <input type="email" name="admin_email" required placeholder="admin@example.com" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-bold mb-1">Master Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full bg-gray-950 border border-gray-800 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors">
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 rounded-lg transition-colors mt-2 shadow-lg shadow-indigo-900/20">
                Install & Initialize System
            </button>
        </form>
        <?php endif; ?>

    </div>
</body>
</html>