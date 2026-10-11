<?php
declare(strict_types=1);

// Include database connection, authentication, and layout templates
require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

// Retrieve current logged-in user session data
$uzivatel = prihlaseny_uzivatel($pdo);

// --- SECTION 1: IDENTIFYING THE SHOW (LOCAL ID vs API ID) ---
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$api_id = filter_var($_GET['api_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

// --- SECTION 2: FETCH AND CACHE EXTERNAL TVMAZE API DATA IF UNKNOWN LOCALLY ---
if ($api_id) {
    // Check if this show is already stored in our database by its API ID
    $stmt = $pdo->prepare('SELECT id FROM serialy WHERE api_id = ?');
    $stmt->execute([$api_id]);
    $local_id = $stmt->fetchColumn();

    // If not found locally, fetch details from TVMaze and insert into database
    if (!$local_id) {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true]]);
        $json = @file_get_contents("https://api.tvmaze.com/shows/{$api_id}?embed[]=seasons&embed[]=episodes", false, $ctx);
        
        if ($json) {
            $data = json_decode($json, true);
            if (isset($data['id'], $data['name'])) {
                
                // Check if the show exists by name to avoid duplicate title entries
                $stmt = $pdo->prepare('SELECT id FROM serialy WHERE nazev = ?');
                $stmt->execute([$data['name']]);
                $local_id = $stmt->fetchColumn();
                $plakat = $data['image']['original'] ?? $data['image']['medium'] ?? null;

                if ($local_id) {
                    // Update existing record with missing API ID and poster URL
                    $pdo->prepare('UPDATE serialy SET api_id = ?, plakat_url = COALESCE(plakat_url, ?) WHERE id = ?')->execute([$api_id, $plakat, $local_id]);
                } else {
                    // Insert a brand new show record into the database
                    $popis = strip_tags($data['summary'] ?? '');
                    $rok = !empty($data['premiered']) ? (int)substr($data['premiered'], 0, 4) : null;
                    $pocet_serii = !empty($data['_embedded']['seasons']) ? count($data['_embedded']['seasons']) : null;
                    $pocet_epizod = !empty($data['_embedded']['episodes']) ? count($data['_embedded']['episodes']) : null;
                    
                    $pdo->prepare('INSERT INTO serialy (nazev, popis, rok_vydani, pocet_serii, pocet_epizod, api_id, plakat_url, vytvoril_uzivatel_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$data['name'], $popis, $rok, $pocet_serii, $pocet_epizod, $api_id, $plakat, 1]);
                    $local_id = (int)$pdo->lastInsertId();

                    // Insert associated genres and link them to the show
                    if (!empty($data['genres'])) {
                        $stmtZanr = $pdo->prepare('INSERT OR IGNORE INTO zanry (nazev) VALUES (?)');
                        $stmtZanrId = $pdo->prepare('SELECT id FROM zanry WHERE nazev = ?');
                        $stmtLink = $pdo->prepare('INSERT INTO serialy_zanry (serial_id, zanr_id) VALUES (?, ?)');
                        foreach ($data['genres'] as $z) {
                            $stmtZanr->execute([$z]);
                            $stmtZanrId->execute([$z]);
                            if ($z_id = $stmtZanrId->fetchColumn()) { $stmtLink->execute([$local_id, $z_id]); }
                        }
                    }
                }
            }
        }
    }
    // Resolve local ID reference
    if ($local_id) $id = (int)$local_id;
}

// --- SECTION 3: FETCH SHOW DATA FROM OUR DATABASE ---
$serial = false;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM serialy WHERE id = ?');
    $stmt->execute([$id]);
    $serial = $stmt->fetch();
}

// Handle 404 error if show ID does not exist
if (!$serial) {
    http_response_code(404);
    hlavicka('Nenalezeno', $uzivatel);
    echo '<div class="pt-40 px-[4vw] text-4xl font-black">SERIÁL NENALEZEN</div>';
    paticka(); exit;
}

// Retrieve show genres
$stmt = $pdo->prepare('SELECT z.nazev FROM serialy_zanry sz JOIN zanry z ON z.id = sz.zanr_id WHERE sz.serial_id = ?');
$stmt->execute([$id]);
$zanry = $stmt->fetchAll();

// Calculate community rating distribution and average score
$stmt = $pdo->prepare('SELECT pocet_hvezd, COUNT(*) AS pocet FROM hodnoceni WHERE serial_id = ? GROUP BY pocet_hvezd');
$stmt->execute([$id]);
$rozlozeni = array_fill(1, 5, 0);
foreach ($stmt->fetchAll() as $r) { $rozlozeni[(int)$r['pocet_hvezd']] = (int)$r['pocet']; }
$pocetHodnoceni = array_sum($rozlozeni);
$prumer = $pocetHodnoceni ? array_sum(array_map(fn($h, $n) => $h * $n, array_keys($rozlozeni), $rozlozeni)) / $pocetHodnoceni : 0.0;

// Fetch current user's personal watchlist status and rating for this show
$mujStav = null; $mojeHodnoceni = null;
if ($uzivatel) {
    $stmt = $pdo->prepare('SELECT stav FROM watchlist WHERE uzivatel_id = ? AND serial_id = ?'); $stmt->execute([$uzivatel['id'], $id]); $mujStav = $stmt->fetchColumn() ?: null;
    $stmt = $pdo->prepare('SELECT id, pocet_hvezd, komentar FROM hodnoceni WHERE uzivatel_id = ? AND serial_id = ?'); $stmt->execute([$uzivatel['id'], $id]); $mojeHodnoceni = $stmt->fetch() ?: null;
}

// --- SECTION 4: FETCH OTHER USERS' REVIEWS (Excluding current user's own review) ---
$stmt = $pdo->prepare(
    'SELECT h.id, h.pocet_hvezd, h.komentar, h.datum_pridani, u.login, u.foto_cesta
       FROM hodnoceni h JOIN uzivatele u ON u.id = h.uzivatel_id
      WHERE h.serial_id = ? AND h.uzivatel_id <> ?
      ORDER BY h.datum_pridani DESC, h.id DESC'
);
$stmt->execute([$id, $uzivatel ? (int)$uzivatel['id'] : 0]);
$hodnoceniOstatnich = $stmt->fetchAll();

$navrat = 'serial.php?id=' . $id;

hlavicka($serial['nazev'], $uzivatel);
?>

<!-- Custom CSS for interactive star rating radio buttons -->
<style>
    .stars-radio { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 10px; margin-bottom: 20px; }
    .stars-radio input { display: none; }
    .stars-radio label { font-size: 30px; color: rgba(255,255,255,0.2); cursor: pointer; transition: color 0.2s; }
    .stars-radio label:hover, .stars-radio label:hover ~ label, .stars-radio input:checked ~ label { color: #fff; }
</style>

<!-- MAIN SHOW DETAIL CONTAINER -->
<main class="w-full max-w-7xl mx-auto pt-[150px] pb-20 px-[4vw] min-h-[calc(100vh-101px)] flex flex-col" id="loaded-content">
    
    <!-- Back to catalog navigation link -->
    <div class="mb-10 self-start back-btn-wrapper">
        <a href="index.php" class="flex items-center gap-2 text-sm font-bold uppercase tracking-widest text-white/60 hover:text-white hover:-translate-x-1 transition-all">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back to Explore
        </a>
    </div>
    
    <!-- Split content layout (Poster on the left, show details on the right) -->
    <div class="flex flex-col md:flex-row gap-[6vw] mb-16 detail-content">
        
        <!-- Poster display wrapper -->
        <div class="w-full md:w-[35%] relative detail-image">
            <div class="w-full aspect-[2/3] rounded-md overflow-hidden bg-epilogCard shadow-[0_20px_50px_rgba(0,0,0,0.5)]">
                <?= plakat_html($serial, 'w-full h-full object-cover') ?>
            </div>
        </div>
        
        <!-- Show metadata and interactive forms container -->
        <div class="flex-1 pt-5 detail-info">
            <h2 class="text-[clamp(40px,6vw,100px)] font-black uppercase leading-none mb-8 tracking-tighter"><?= h($serial['nazev']) ?></h2>
            
            <!-- Metadata summary bar (Rating, Seasons, Episodes, Release Year) -->
            <div class="flex flex-wrap gap-10 text-lg font-bold uppercase border-y-2 border-white/15 py-6 mb-8 text-white">
                <?php if ($pocetHodnoceni > 0): ?>
                    <span class="flex items-center opacity-90 gap-2">★ <?= cislo_cs($prumer) ?> <small class="font-light opacity-50">(<?= $pocetHodnoceni ?>)</small></span>
                <?php else: ?>
                    <span class="opacity-50">ZATÍM NEHODNOCENO</span>
                <?php endif; ?>
                <span class="flex items-center opacity-90"><?= $serial['pocet_serii'] ?: '?' ?> SEASONS</span>
                <span class="flex items-center opacity-90"><?= $serial['pocet_epizod'] ?: '?' ?> EPISODES</span>
                <span class="flex items-center opacity-90"><?= h((string)$serial['rok_vydani']) ?></span>
            </div>
            
            <!-- Genres list -->
            <div class="uppercase text-sm text-white/50 font-bold mb-5">
                <?= implode(' / ', array_column($zanry, 'nazev')) ?>
            </div>

            <!-- Show plot summary -->
            <div class="text-lg leading-relaxed text-white/70 max-w-3xl mb-10">
                <?= nl2br(h($serial['popis'])) ?>
            </div>
            
            <!-- USER INTERACTION PANELS (Watchlist and Rating forms) -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-10 mt-10 border-t-2 border-white/15 pt-10">
                
                <!-- WATCHLIST FORM PANEL -->
                <div>
                    <h3 class="text-2xl font-black uppercase mb-5">Watchlist</h3>
                    <?php if ($uzivatel): ?>
                        <form action="watchlist.php" method="POST" class="flex flex-row gap-2">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="serial_id" value="<?= $id ?>">
                            <input type="hidden" name="akce" value="nastav">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                            
                            <?php foreach (STAVY_WATCHLISTU as $klic => $popis): ?>
                                <button type="submit" name="stav" value="<?= h($klic) ?>" class="border border-white/20 text-white px-3 py-2 text-xs font-bold uppercase transition-colors hover:bg-white hover:text-epilogBg <?= $mujStav === $klic ? 'bg-white text-epilogBg' : 'bg-transparent' ?>"><?= h($popis) ?></button>
                            <?php endforeach; ?>
                        </form>
                        
                        <?php if ($mujStav): ?>
                            <form action="watchlist.php" method="POST" class="mt-4">
                                <?= csrf_pole() ?>
                                <input type="hidden" name="serial_id" value="<?= $id ?>"><input type="hidden" name="akce" value="odeber"><input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                                <button type="submit" class="tl-odkaz tl-nebezpecne text-xs">Remove from Watchlist</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-white/50">Please <a href="<?= h(STRANA_PRIHLASENI) ?>" class="text-white underline font-bold">Login</a> to save to watchlist.</p>
                    <?php endif; ?>
                </div>

                <!-- SHOW RATING FORM PANEL -->
                <div>
                    <h3 class="text-2xl font-black uppercase mb-5">Rate Show</h3>
                    <?php if ($uzivatel): ?>
                        <form action="hodnoceni.php" method="POST">
                            <?= csrf_pole() ?>
                            <input type="hidden" name="akce" value="uloz">
                            <input type="hidden" name="serial_id" value="<?= $id ?>">
                            <input type="hidden" name="navrat" value="<?= h($navrat) ?>">

                            <div class="stars-radio">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="hvezdy" id="hv<?= $i ?>" value="<?= $i ?>" <?= $mojeHodnoceni && (int)$mojeHodnoceni['pocet_hvezd'] === $i ? 'checked' : '' ?> required>
                                    <label for="hv<?= $i ?>">★</label>
                                <?php endfor; ?>
                            </div>

                            <textarea name="komentar" rows="3" class="brutal-input resize-y mb-4" placeholder="What did you think? (Optional)"><?= h($mojeHodnoceni['komentar'] ?? '') ?></textarea>
                            <button type="submit" class="tl tl-primarni"><?= $mojeHodnoceni ? 'Update Rating' : 'Submit Rating' ?></button>
                        </form>
                        
                        <?php if ($mojeHodnoceni): ?>
                            <form action="hodnoceni.php" method="POST" class="mt-4">
                                <?= csrf_pole() ?>
                                <input type="hidden" name="akce" value="smaz"><input type="hidden" name="hodnoceni_id" value="<?= (int)$mojeHodnoceni['id'] ?>"><input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                                <button type="submit" class="tl-odkaz tl-nebezpecne text-xs" onclick="return confirm('Delete your rating?');">Delete Rating</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-white/50">Please <a href="<?= h(STRANA_PRIHLASENI) ?>" class="text-white underline font-bold">Login</a> to rate this show.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- REVIEWS LIST SECTION -->
            <div class="mt-16">
                <h3 class="text-2xl font-black uppercase mb-5 border-b-2 border-white/15 pb-2">Reviews</h3>
                <?php if (!$hodnoceniOstatnich): ?>
                    <p class="text-white/50">No reviews yet.</p>
                <?php else: ?>
                    <ul class="mt-5 list-none">
                        <?php foreach ($hodnoceniOstatnich as $r): ?>
                            <li class="border-b border-white/10 py-5 flex items-start gap-5">
                                <img src="<?= h($r['foto_cesta']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover">
                                <div>
                                    <div class="font-black uppercase text-base mb-1">
                                        <?= h($r['login']) ?> <span class="font-light opacity-50 text-xs ml-2"><?= datum_cs($r['datum_pridani']) ?></span>
                                    </div>
                                    <div class="mb-2"><?= hvezdy_html((float)$r['pocet_hvezd']) ?></div>
                                    <?php if ($r['komentar']): ?>
                                        <div class="text-white/70 text-[15px] leading-relaxed"><?= nl2br(h($r['komentar'])) ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Admin moderation delete option -->
                                    <?php if ($uzivatel && $uzivatel['role'] === 'admin'): ?>
                                        <form action="hodnoceni.php" method="POST" class="mt-2">
                                            <?= csrf_pole() ?>
                                            <input type="hidden" name="akce" value="smaz"><input type="hidden" name="hodnoceni_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="navrat" value="<?= h($navrat) ?>">
                                            <button type="submit" class="tl-odkaz tl-nebezpecne text-[11px]" onclick="return confirm('Smazat jako admin?');">Smazat (Admin)</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</main>

<!-- GSAP entry animations timeline -->
<script>
    document.addEventListener("DOMContentLoaded", () => {
        const tl = gsap.timeline();
        tl.from(".nav-element", { y: -50, opacity: 0, duration: 0.8, ease: "power3.out" })
          .from(".back-btn-wrapper", { x: -30, opacity: 0, duration: 0.6, ease: "power2.out" }, "-=0.4")
          .fromTo(".detail-image", { y: 80, opacity: 0, scale: 0.95 }, { y: 0, opacity: 1, scale: 1, duration: 1, ease: "expo.out" }, "-=0.4")
          .from(".detail-info > *", { y: 40, opacity: 0, duration: 0.8, stagger: 0.15, ease: "power3.out" }, "-=0.8");
    });
</script>

<?php 
// Render page footer
paticka(); 
?>