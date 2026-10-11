<?php
declare(strict_types=1);

/**
 * Schránka přijatých zpráv a oznámení přihlášeného uživatele (tabulka `zpravy`, notifikace v `notifikace`).
 * Předmět i text jsou v DB šifrované (php/crypto.php) a dešifrují se až tady.
 * Otevření stránky označí zprávy a notifikace jako přečtené .
 */

require __DIR__ . '/php/db.php';
require __DIR__ . '/php/auth.php';
require __DIR__ . '/php/crypto.php';
require __DIR__ . '/php/layout.php';

$uzivatel = vyzaduj_prihlaseni($pdo);
$uid = (int)$uzivatel['id'];

// ---------- smazání zprávy (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_vyzaduj();
    $id = filter_var($_POST['zprava_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id !== false && $id !== null) {
        // WHERE prijemce_id = ? zaručí, že jde smazat jen zprávu určenou mně
        $stmt = $pdo->prepare('UPDATE zpravy SET smazal_prijemce = 1, precteno = 1 WHERE id = ? AND prijemce_id = ?');
        $stmt->execute([$id, $uid]);
        if ($stmt->rowCount() > 0) {
            $pdo->prepare('UPDATE notifikace SET precteno = 1 WHERE zprava_id = ? AND uzivatel_id = ?')->execute([$id, $uid]);
            flash('ok', 'Zpráva byla smazána.');
        }
    }
    header('Location: zpravy.php');
    exit;
}

// ---------- načtení ----------
$stmt = $pdo->prepare(
    'SELECT z.id, z.predmet, z.sifrovany_text, z.cas_odeslani, z.precteno, u.jmeno, u.prijmeni, u.role
       FROM zpravy z
       JOIN uzivatele u ON u.id = z.odesilatel_id
      WHERE z.prijemce_id = ? AND z.smazal_prijemce = 0
      ORDER BY z.cas_odeslani DESC, z.id DESC
      LIMIT 100'
);
$stmt->execute([$uid]);
$zpravy = [];
foreach ($stmt->fetchAll() as $r) {
    try {
        $r['predmet'] = decrypt((string)$r['predmet']);
        $r['text'] = decrypt((string)$r['sifrovany_text']);
    } catch (Throwable $e) {
        error_log('zpravy.php: ' . $e->getMessage());
        $r['predmet'] = '(zprávu se nepodařilo dešifrovat)';
        $r['text'] = '';
    }
    $zpravy[] = $r;
}
$novych = count(array_filter($zpravy, static fn(array $z): bool => (int)$z['precteno'] === 0));

$pdo->prepare('UPDATE zpravy SET precteno = 1 WHERE prijemce_id = ? AND precteno = 0')->execute([$uid]);
$pdo->prepare('UPDATE notifikace SET precteno = 1 WHERE uzivatel_id = ? AND precteno = 0')->execute([$uid]);

/** Čas z DB (UTC) -> "12. 9. 2026 14:05" v pražském čase. */
function cas_cs(?string $utc): string
{
    $d = $utc ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $utc, new DateTimeZone('UTC')) : false;
    return $d ? $d->setTimezone(new DateTimeZone('Europe/Prague'))->format('j. n. Y H:i') : '';
}

hlavicka('Zprávy', $uzivatel, 'zpravy');
?>
<main class="w-full max-w-4xl mx-auto pt-[150px] pb-20 px-[4vw]">
<h1 class="text-4xl sm:text-6xl font-black uppercase tracking-tighter mb-10">Zprávy a oznámení</h1>
<?php if ($novych > 0): ?>
    <p class="text-sm font-bold uppercase tracking-widest text-white/60 mb-6">Nových: <?= $novych ?></p>
<?php endif; ?>

<?php if (!$zpravy): ?>
    <div class="bg-epilogCard border border-white/10 rounded p-8 text-white/50">Schránka je prázdná.</div>
<?php endif; ?>

<?php foreach ($zpravy as $z): ?>
    <article class="bg-epilogCard border border-white/10 rounded p-6 mb-5<?= (int)$z['precteno'] === 0 ? ' border-l-4 !border-l-white' : '' ?>">
        <header class="flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-black uppercase break-words"><?= h($z['predmet']) ?></h2>
            <?php if ((int)$z['precteno'] === 0): ?><span class="bg-white text-epilogBg text-xs font-bold uppercase px-2 py-0.5">Nové</span><?php endif; ?>
        </header>
        <p class="text-xs text-white/50 mt-2">
            Od: <?= h($z['jmeno'] . ' ' . $z['prijmeni']) ?>
            <?php if ($z['role'] === 'admin'): ?><span class="ml-1 bg-white/10 text-white/70 font-bold uppercase px-2 py-0.5">Administrátor</span><?php endif; ?>
            · <?= h(cas_cs($z['cas_odeslani'])) ?>
        </p>
        <p class="mt-4 text-white/80 leading-relaxed break-words"><?= nl2br(h($z['text'])) ?></p>
        <form action="zpravy.php" method="POST" class="mt-4">
            <?= csrf_pole() ?>
            <input type="hidden" name="zprava_id" value="<?= (int)$z['id'] ?>">
            <button type="submit" class="tl-odkaz tl-nebezpecne text-xs">Smazat</button>
        </form>
    </article>
<?php endforeach; ?>
</main>
<?php
paticka();
