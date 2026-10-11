<?php
declare(strict_types=1);

// Include database connection, authentication, and layout templates
require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/layout.php';

// Retrieve current logged-in user session data
$uzivatel = prihlaseny_uzivatel($pdo);

// Render the page header with title and navigation state
hlavicka('Katalog seriálů', $uzivatel, 'katalog');
?>

<!-- STYLY JEN PRO GSAP ANIMACI -->
<style>
    /* Hero Text Animation Styles */
    .brutal-hero { overflow: hidden; width: 100%; height: 100vh; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-start; background-color: #0d0f12; position: relative; z-index: 50; padding: 12vw 4vw 6vw 4vw; }
    .hero-content { display: flex; flex-direction: column; align-items: flex-start; z-index: 2; }
    .brutal-text-wrapper { overflow: hidden; line-height: 0.85; padding-bottom: 2vh; }
    .brutal-line { font-size: clamp(60px, 12vw, 250px); font-weight: 900; text-transform: uppercase; margin: 0; letter-spacing: -0.03em; white-space: nowrap; color: #ffffff; display: flex; }
    .char { display: inline-block; will-change: transform; }

    /* Parallax Scroll Animation Styles (TOP 5 Section) */
    .hero-wrapper { width: 100%; height: 100vh; position: relative; overflow: hidden; z-index: 10; background-color: #000; }
    .backdrop-container { position: absolute; inset: 0; z-index: 1; pointer-events: none; background-color: #000; }
    .backdrop-layer { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.85s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .backdrop-layer.active { opacity: 1; }
    .vignette-overlay { position: absolute; inset: 0; z-index: 2; background-color: rgba(0, 0, 0, 0.4); }
    
    .shows-stack { position: absolute; left: 4vw; right: 4vw; top: 340px; display: flex; flex-direction: column; z-index: 20; }
    .show-row-wrapper { display: grid; grid-template-rows: 1fr; transition: grid-template-rows 0.5s cubic-bezier(0.2, 0.8, 0.2, 1); }
    .show-row-wrapper.collapsed { grid-template-rows: 0fr; pointer-events: none; }
    .show-row-inner { overflow: hidden; min-height: 0; }
    
    /* Row styling with adjusted padding to prevent text cutoff */
    .show-row { display: flex; justify-content: space-between; align-items: flex-end; padding: 35px 0; border-bottom: 2px solid rgba(255, 255, 255, 0.95); cursor: pointer; }
    .show-row.inactive .show-num, .show-row.inactive .show-name { opacity: 0.35; transition: opacity 0.3s ease; }
    .show-row.active .show-num, .show-row.active .show-name { opacity: 1; transition: opacity 0.3s ease; }

    @media (max-width: 600px) {
        .brutal-hero { padding: 30vw 6vw 12vw; }
        .brutal-line { font-size: clamp(45px, 15vw, 100px); }
        .shows-stack { top: 160px; }
        .show-row { padding: 12px 0; }
    }
</style>

<!-- 1. ÚVODNÍ VELKÝ TEXT -->
<section class="brutal-hero" id="brutal-hero">
    <div class="hero-content">
        <div class="brutal-text-wrapper"><h1 class="brutal-line">ONLY</h1></div>
        <div class="brutal-text-wrapper"><h1 class="brutal-line">THE BEST</h1></div>
        <div class="brutal-text-wrapper"><h1 class="brutal-line">STORIES.</h1></div>
    </div>
</section>

<!-- 2. ANIMOVANÝ SCROLL TOP 5 -->
<div class="hero-wrapper" id="hero-wrapper">
    <div class="backdrop-container" id="backdrop-container"><div class="vignette-overlay"></div></div>
    <main>
        <div class="absolute left-[4vw] top-[300px] max-[600px]:top-[120px] text-base max-[600px]:text-[13px] font-bold tracking-widest uppercase z-20 text-white gsap-row">TOP TV SHOWS</div>
        <div class="shows-stack" id="shows-stack"></div>
    </main>
</div>

<!-- 3. SEKCE EXPLORE -->
<section class="relative z-20 bg-epilogBg min-h-screen pt-24 pb-40 px-[4vw] shadow-[0_-30px_60px_rgba(0,0,0,0.5)]" id="explore-section" x-data="exploreApp()">
    
    <h2 class="text-[clamp(60px,10vw,150px)] font-black tracking-tighter uppercase leading-none mb-12">EXPLORE</h2>
    
    <!-- Filter Controls Bar -->
    <div class="flex flex-wrap items-center gap-5 sm:gap-8 mb-16 text-sm filters-bar">
        <div class="uppercase font-medium tracking-widest text-white/50">FILTER:</div>
        
        <div class="flex items-center gap-3">
            <label class="hidden sm:block uppercase">Genre</label>
            <select x-model="filters.genre" class="bg-transparent border border-white/20 text-white py-2 px-4 rounded outline-none font-medium">
                <option value="all" class="bg-epilogCard">All</option>
                <template x-for="genre in availableGenres"><option :value="genre.toLowerCase()" x-text="genre" class="bg-epilogCard"></option></template>
            </select>
        </div>
        
        <div class="flex items-center gap-3">
            <label class="hidden sm:block uppercase">Release Date:</label>
            <select x-model="filters.date" class="bg-transparent border border-white/20 text-white py-2 px-4 rounded outline-none font-medium">
                <option value="all" class="bg-epilogCard">All Time</option>
                <option value="2020" class="bg-epilogCard">2020s</option>
                <option value="2010" class="bg-epilogCard">2010s</option>
                <option value="older" class="bg-epilogCard">Older</option>
            </select>
        </div>
        
        <div class="flex items-center gap-3 ml-auto">
            <label class="uppercase">Sort By:</label>
            <select x-model="filters.sort" class="bg-transparent border border-white/20 text-white py-2 px-4 rounded outline-none font-medium">
                <option value="name" class="bg-epilogCard">Name A-Z</option>
                <option value="newest" class="bg-epilogCard">Newest</option>
            </select>
        </div>
    </div>

    <!-- Shows Grid Layout -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-[3vw] gap-y-10 xl:gap-y-16 explore-grid" id="explore-grid">
        <template x-for="show in filteredShows" :key="show.id">
            <a class="explore-card flex flex-col gap-4 text-white group cursor-pointer" :href="'serial.php?api_id=' + show.id">
                <div class="flex justify-between items-start gap-2">
                    <span class="text-base lg:text-xl font-bold leading-tight tracking-wide">
                        <span x-text="show.name"></span><br>
                        <span class="font-light text-sm opacity-70" x-text="show.premiered ? show.premiered.substring(0,4) : ''"></span>
                    </span>
                </div>
                <div class="w-full rounded-md overflow-hidden bg-epilogCard shadow-lg transition-shadow duration-400 group-hover:shadow-[0_20px_40px_rgba(0,0,0,0.7)] relative aspect-[2/3]">
                    <img :src="show.image ? (show.image.original || show.image.medium) : 'https://via.placeholder.com/400x600?text=NO+IMAGE'" 
                         loading="lazy" alt="Poster"
                         class="w-full h-full object-cover block transition-all duration-700 ease-out group-hover:scale-105 group-hover:brightness-110">
                </div>
            </a>
        </template>
    </div>
</section>

<!-- JavaScript for GSAP Animations and Alpine.js Data Fetching -->
<script>
    // Split brutal hero header text into individual characters for stagger animation
    document.querySelectorAll('.brutal-line').forEach(line => {
        line.innerHTML = line.textContent.split('').map(char => char === ' ' ? `<span class="char" style="width: 0.25em;">&nbsp;</span>` : `<span class="char">${char}</span>`).join('');
    });
    if (document.querySelector('.char')) {
        gsap.set(".char", { y: "150%", skewY: 8 });
        gsap.timeline()
            .to(".char", { y: "0%", skewY: 0, duration: 1.2, stagger: 0.04, ease: "expo.out", delay: 0.2 })
            .from(".nav-element", { y: -30, opacity: 0, duration: 1, ease: "power3.out" }, "-=1");
        
        gsap.to(".hero-content", { scrollTrigger: { trigger: "#brutal-hero", start: "top top", end: "bottom top", scrub: true }, y: -150, opacity: 0 });
    }

    // Static dataset for the interactive TOP 5 scroll section
    const TOP_SHOWS = [
        { num: "01.", title: "Breaking Bad", image: "images/breakingbad.jpg" },
        { num: "02.", title: "The Sopranos", image: "images/sopranos.avif" },
        { num: "03.", title: "Chernobyl", image: "images/chernobyl.jpg" },
        { num: "04.", title: "Narcos", image: "images/narcos.jpeg" },
        { num: "05.", title: "Succession", image: "images/succession.jpg" }
    ];

    const heroWrapper = document.getElementById('hero-wrapper');
    if (heroWrapper && TOP_SHOWS.length > 0) {
        let currentIndex = 0; const scrollDistance = 4000; 
        
        // Dynamically prepend backdrop image layers
        [...TOP_SHOWS].reverse().forEach((show, revIdx) => {
            const idx = TOP_SHOWS.length - 1 - revIdx;
            const img = document.createElement('img');
            img.className = `backdrop-layer ${idx === 0 ? 'active' : ''}`; img.id = `backdrop-${idx}`; img.src = show.image; 
            document.getElementById('backdrop-container').prepend(img);
        });

        // Generate stacked list rows for TOP 5 shows
        TOP_SHOWS.forEach((show, idx) => {
            const wrapper = document.createElement('div'); wrapper.className = `show-row-wrapper ${idx === 0 ? 'active-slot gsap-row' : 'gsap-row'}`; wrapper.id = `row-wrapper-${idx}`;
            
            wrapper.innerHTML = `<div class="show-row-inner"><div class="show-row ${idx === 0 ? 'active' : 'inactive'}" id="row-${idx}"><div class="flex items-baseline gap-2 sm:gap-6 w-full"><span class="show-num font-black text-xl sm:text-[clamp(22px,3vw,42px)] text-white shrink-0">${show.num}</span><span class="show-name font-black text-3xl sm:text-[clamp(36px,5.2vw,85px)] uppercase text-white truncate"><?= h($show['title'] ?? '') ?></span></div></div></div>`;
            
            wrapper.innerHTML = `<div class="show-row-inner"><div class="show-row ${idx === 0 ? 'active' : 'inactive'}" id="row-${idx}"><div class="flex items-baseline gap-2 sm:gap-6 w-full"><span class="show-num font-black text-xl sm:text-[clamp(22px,3vw,42px)] text-white shrink-0">${show.num}</span><span class="show-name font-black text-3xl sm:text-[clamp(36px,5.2vw,85px)] uppercase text-white">${show.title}</span></div></div></div>`;

            // Smooth scroll navigation trigger on row click
            wrapper.addEventListener('click', () => { 
                let targetScroll = window.innerHeight + (scrollDistance / (TOP_SHOWS.length - 1)) * idx;
                gsap.to(window, { scrollTo: targetScroll, duration: 1, ease: "power2.inOut" });
            });
            document.getElementById('shows-stack').appendChild(wrapper);
        });

        // GSAP ScrollTrigger configuration for pinning and syncing active indices
        ScrollTrigger.create({
            trigger: "#hero-wrapper", start: "top top", end: `+=${scrollDistance}`, pin: true, pinSpacing: true,
            onUpdate: (self) => {
                let newIndex = Math.round(self.progress * (TOP_SHOWS.length - 1));
                if (newIndex !== currentIndex) {
                    currentIndex = newIndex;
                    document.querySelectorAll('.backdrop-layer').forEach((layer, idx) => layer.classList.toggle('active', idx === currentIndex));
                    TOP_SHOWS.forEach((_, idx) => {
                        document.getElementById(`row-wrapper-${idx}`).classList.toggle('collapsed', idx < currentIndex);
                        document.getElementById(`row-${idx}`).classList.toggle('active', idx === currentIndex);
                        document.getElementById(`row-${idx}`).classList.toggle('inactive', idx !== currentIndex);
                    });
                }
            }
        });
        gsap.from(".gsap-row", { scrollTrigger: { trigger: "#hero-wrapper", start: "top 70%" }, y: 40, opacity: 0, duration: 0.8, stagger: 0.1, ease: "power3.out" });
        ScrollTrigger.refresh();
    }

    gsap.from(".explore-title, .filters-bar", { scrollTrigger: { trigger: ".explore-section", start: "top 75%" }, y: 60, opacity: 0, duration: 0.8, stagger: 0.2, ease: "back.out(1.5)" });

    // Alpine.js data component for catalog items and API filtering
    document.addEventListener('alpine:init', () => {
        Alpine.data('exploreApp', () => ({
            exploreData: [], availableGenres: [], filters: { genre: 'all', date: 'all', sort: 'name' },
            async init() {
                try {
                    const response = await fetch('https://api.tvmaze.com/shows');
                    this.exploreData = (await response.json()).slice(0, 100);
                    const genresSet = new Set(); this.exploreData.forEach(s => s.genres.forEach(g => genresSet.add(g))); this.availableGenres = Array.from(genresSet).sort();
                    
                    this.$nextTick(() => { gsap.fromTo(".explore-card", { y: 80, opacity: 0 }, { scrollTrigger: { trigger: ".explore-section", start: "top 50%" }, y: 0, opacity: 1, duration: 0.6, stagger: 0.05, ease: "back.out(1.2)" }); });
                } catch(e) { console.error("Chyba API Explore:", e); }
            },
            get filteredShows() {
                let filtered = this.exploreData.filter(show => {
                    if (this.filters.genre !== 'all' && !show.genres.map(g => g.toLowerCase()).includes(this.filters.genre)) return false;
                    const year = show.premiered ? parseInt(show.premiered.substring(0,4)) : 0;
                    if (this.filters.date === '2020' && (year < 2020 || year >= 2030)) return false;
                    if (this.filters.date === '2010' && (year < 2010 || year >= 2020)) return false;
                    if (this.filters.date === 'older' && year >= 2010) return false; return true;
                });
                
                if (this.filters.sort === 'name') filtered.sort((a,b) => a.name.localeCompare(b.name));
                else if (this.filters.sort === 'newest') filtered.sort((a,b) => new Date(b.premiered || 0) - new Date(a.premiered || 0));
                return filtered;
            }
        }));
    });
</script>

<?php 
// Render the standard page footer
paticka(); 
?>