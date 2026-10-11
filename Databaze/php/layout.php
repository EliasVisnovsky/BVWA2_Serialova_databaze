<?php
declare(strict_types=1);

/**
 * Escapes special characters to prevent XSS attacks.
 */
function h(?string $text): string { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }

// Predefined localization labels for user watchlist states
const STAVY_WATCHLISTU = ['chci_videt' => 'Chci vidět', 'sleduji' => 'Sleduji', 'videno' => 'Viděno'];

/**
 * Formats numbers into Czech decimal standards.
 */
function cislo_cs(float $c, int$mista = 1): string { return number_format($c,$mista, ',', ''); }

/**
 * Converts UTC database timestamps into localized Prague time strings.
 */
function datum_cs(?string $utc): string {
    if (!$utc) return '';
    $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$utc, new DateTimeZone('UTC'));
    return $d ? $d->setTimezone(new DateTimeZone('Europe/Prague'))->format('j. n. Y') : '';
}

/**
 * Handles proper Czech grammatical declension for words based on counts.
 */
function sklonuj(int $n, string$jedna, string $dve_ctyri, string$pet): string {
    if ($n === 1) return$jedna; return ($n >= 2 &&$n <= 4) ? $dve_ctyri :$pet;
}

/**
 * Formats complete season and episode counters.
 */
function epizody_text(?int $serii, ?int $epizod): string {
    if (!$epizod) return 'neuvedeno';$t = $epizod . ' ' . sklonuj($epizod, 'epizoda', 'epizody', 'epizod');
    if ($serii)$t .= ' · ' . $serii . ' ' . sklonuj($serii, 'série', 'série', 'sérií');
    return $t;
}

/**
 * Generates filled and transparent star rating icons based on a numeric score value.
 */
function hvezdy_html(float $hodnota): string {
    $plne = max(0, min(5, (int)round($hodnota)));
    return '<span class="text-white text-lg" aria-label="' . h(cislo_cs($hodnota)) . ' z 5">'
        . str_repeat('★', $plne) . '<span class="opacity-30">' . str_repeat('★', 5 - $plne) . '</span></span>';
}

/**
 * Renders the show poster image or displays a fallback 'NO IMAGE' box if missing.
 */
function plakat_html(array $serial, string $trida = ''): string {$url = (string)($serial['plakat_url'] ?? '');$nahradni_plakaty = [
        'Breaking Bad' => 'https://static.tvmaze.com/uploads/images/medium_portrait/0/2400.jpg',
        'Chernobyl' => 'https://static.tvmaze.com/uploads/images/medium_portrait/189/474136.jpg',
    ];
    if (!$url && isset($nahradni_plakaty[$serial['nazev']])) {$url = $nahradni_plakaty[$serial['nazev']]; }

    if ($url) { return '<img class="' . h($trida) . '" src="' . h($url) . '" alt="Plakát" loading="lazy">'; }
    return '<div class="plakat-nahrada ' . h($trida) . ' bg-[#1a1a1a] flex items-center justify-center text-2xl font-black text-white/20">NO IMAGE</div>';
}

/**
 * Outputs the HTML document head, loads external frameworks, and renders top navigation.
 */
function hlavicka(string $titulek, ?array $uzivatel, string $aktivni = ''): void {$flash = flash_vyber();
    ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulek) ?> – EPILOG</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">

    <!-- External Frameworks: GSAP, Alpine.js, Tailwind CSS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollToPlugin.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: { colors: { epilogBg: '#0d0f12', epilogCard: '#1a1a1a' }, fontFamily: { sans: ['Roboto', 'sans-serif'] } }
            }
        }
    </script>
    <style type="text/tailwindcss">
        /* Hides elements with x-cloak until Alpine.js fully initializes (prevents startup flashing) */
        [x-cloak] { display: none !important; }

        @layer base {
            body { @apply bg-epilogBg text-white font-sans min-h-screen flex flex-col select-none; }
        }
        @layer components {
            .brutal-btn { @apply w-full bg-white text-epilogBg py-4 font-black uppercase tracking-widest transition-transform hover:-translate-y-1 hover:bg-gray-200 disabled:opacity-50; }
            .brutal-input { @apply w-full p-3.5 bg-epilogCard border border-white/10 text-white font-sans text-[15px] rounded outline-none transition-colors focus:border-white focus:bg-[#222]; }
            .form-label { @apply block text-xs uppercase font-bold tracking-wider text-white/60 mb-2; }
            .tl { @apply bg-transparent border border-white/20 text-white px-5 py-2.5 font-bold uppercase transition-colors hover:bg-white hover:text-epilogBg; }
            .tl-primarni { @apply bg-white text-epilogBg; }
            .tl-odkaz { @apply bg-transparent text-white/50 font-bold uppercase underline hover:text-white transition-colors; }
            .tl-nebezpecne { @apply hover:bg-red-500 hover:border-red-500 hover:text-white hover:no-underline px-3 py-1; }
        }
    </style>
</head>
<body x-data="globalSearchApp()">

    <!-- Main Navigation Bar -->
    <nav class="nav-element fixed top-0 left-0 w-full px-[4vw] pt-[37px] pb-4 flex flex-wrap justify-between items-center z-[999] bg-gradient-to-b from-epilogBg/90 to-transparent">
        <div class="flex items-center gap-6 w-[70%] sm:w-auto">
            <span class="text-[15px] text-white/90 cursor-pointer hover:text-white transition-opacity">Messages</span>
            <button class="text-white hover:scale-110 transition-transform" @click="openSearch()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </button>
        </div>

        <div class="w-full text-center sm:absolute sm:left-1/2 sm:top-5 sm:-translate-x-1/2 sm:w-auto order-first sm:order-none mb-4 sm:mb-0">
            <a href="index.php" class="text-4xl sm:text-5xl font-normal uppercase text-white tracking-tight hover:opacity-80 transition-opacity">EPILOG</a>
        </div>

        <div class="flex items-center gap-5 w-[30%] sm:w-auto justify-end">
            <?php if ($uzivatel): ?>
                <a href="profil.php" class="text-[15px] text-white/90 hover:text-white"><?= h($uzivatel['jmeno']) ?></a>
                <form action="logout.php" method="POST" class="m-0">
                    <?= csrf_pole() ?>
                    <button type="submit" class="tl-odkaz text-xs">Logout</button>
                </form>
            <?php else: ?>
                <a href="<?= h(STRANA_PRIHLASENI) ?>" class="text-[15px] text-white/90 hover:text-white">Login</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Global Search Modal Overlay (Managed via Alpine.js) -->
    <div class="fixed inset-0 bg-epilogBg/98 z-[9999] flex flex-col items-center pt-[100px] px-[4vw] transition-all duration-300"
         x-cloak
         :class="isSearchModalOpen ? 'opacity-100 visible' : 'opacity-0 invisible'"
         @keydown.escape.window="closeSearch()">
        
        <div class="w-full max-w-3xl relative">
            <button class="absolute -top-12 right-0 text-white/50 hover:text-white hover:scale-125 hover:rotate-90 text-4xl transition-all" @click="closeSearch()">&times;</button>
            
            <input type="text" x-model="searchQuery" @input.debounce.500ms="performSearch()" 
                   placeholder="SEARCH SHOWS..." class="w-full bg-transparent border-b-2 border-white/20 pb-4 text-3xl sm:text-4xl font-black text-white uppercase outline-none transition-colors focus:border-white/90" x-ref="searchInput">
            
            <div class="w-full max-w-3xl mt-10 max-h-[60vh] overflow-y-auto pr-5">
                <template x-if="searchStatus !== ''"><div class="text-sm uppercase tracking-widest text-white/30 py-8 text-center" x-text="searchStatus"></div></template>
                <template x-for="item in searchResults" :key="item.show.id">
                    <div class="flex justify-between items-baseline py-5 border-b border-white/5 cursor-pointer opacity-50 hover:opacity-100 hover:translate-x-4 hover:border-white/40 transition-all duration-400"
                         @click="window.location.href = 'serial.php?api_id=' + item.show.id">
                        <div class="text-lg sm:text-xl font-black uppercase" x-text="item.show.name"></div>
                        <div class="text-sm font-medium text-white/70" x-text="item.show.premiered ? item.show.premiered.substring(0, 4) : '----'"></div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- Alpine.js component logic for live searching -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('globalSearchApp', () => ({
                isSearchModalOpen: false, searchQuery: '', searchResults: [], searchStatus: '',
                openSearch() {
                    this.isSearchModalOpen = true; this.searchQuery = ''; this.searchResults = []; this.searchStatus = '';
                    setTimeout(() => this.$refs.searchInput.focus(), 100);
                },
                closeSearch() { this.isSearchModalOpen = false; },
                async performSearch() {
                    if (!this.searchQuery.trim()) { this.searchResults = []; this.searchStatus = ''; return; }
                    this.searchStatus = 'SEARCHING...';
                    try {
                        const response = await fetch(`https://api.tvmaze.com/search/shows?q=${this.searchQuery}`);
                        this.searchResults = await response.json();
                        this.searchStatus = this.searchResults.length === 0 ? 'NOTHING FOUND.' : '';
                    } catch (error) { this.searchStatus = 'API CONNECTION ERROR.'; }
                }
            }));
        });
    </script>

    <?php if ($flash): ?>
        <div class="w-full py-5 px-[4vw] font-bold uppercase text-center mt-[100px] text-white <?= $flash['typ'] === 'chyba' ? 'bg-red-500' : 'bg-white/10' ?>">
            <?= h($flash['zprava']) ?>
        </div>
    <?php endif; ?>

<?php
}

/**
 * Closes the HTML document and renders the copyright footer.
 */
function paticka(): void {
?>
    <footer class="mt-auto relative z-20 bg-epilogBg border-t border-white/5 py-10 px-[4vw] flex justify-center text-sm text-white/50">
        <div>&copy; 2026 EPILOG. Všechna práva vyhrazena.</div>
    </footer>
</body>
</html>
<?php
}
?>