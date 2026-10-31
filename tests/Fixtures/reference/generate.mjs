// Records what the reference implementation of @dskripchenko/board does with
// random batches, for the PHP port to be checked against
// (tests/Unit/ReferenceFixturesTest.php, tests/Feature/ServerReferenceTest.php).
//
//   node tests/Fixtures/reference/generate.mjs /path/to/board/dist/index.mjs
//
// The output, reference.json next to this script, is committed: the tests do
// not need node. Seeds are fixed, so a re-run with the same board version
// gives the same file.
import { writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const dist = process.argv[2]
if (!dist) {
  console.error('usage: node generate.mjs /path/to/board/dist/index.mjs')
  process.exit(1)
}
const { applyOps, BoardServer, emptyBoard, META } = await import(pathToFileURL(resolve(dist)).href)

function rng(seed) {
  let a = seed >>> 0
  return () => {
    a = (a + 0x6d2b79f5) >>> 0
    let t = a
    t = Math.imul(t ^ (t >>> 15), t | 1)
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61)
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296
  }
}

const TYPES = ['shape', 'sticky', 'text', 'frame', 'connector', 'emoji', 'ink']
const IDS = ['o1', 'o2', 'o3', 'o4', 'o5', 'o6', 'o7', 'o8', '7', '10', '0', 'x/y', 'ключ']
const TEXTS = ['', 'Hello', 'Привет, мир', 'a/b "quoted" \\ back', '🙂 emoji', 'line\nbreak', '0']

function gen(r) {
  const pick = (xs) => xs[Math.floor(r() * xs.length)]
  const int = (n) => Math.floor(r() * n)
  const num = () => pick([0, 1, 2, int(500) - 250, (int(4000) - 2000) / 8, 1e-7, 123456789012])
  const value = (depth = 0) => {
    const roll = r()
    if (roll < 0.25) return num()
    if (roll < 0.45) return pick(TEXTS)
    if (roll < 0.55) return r() < 0.5
    if (roll < 0.65) return null
    if (depth > 1) return num()
    if (roll < 0.8) return pick([{}, { id: pick(IDS) }, { id: pick(IDS), side: pick(['top', 'left']) }, { x: num(), y: num() }])
    return pick([[], [num(), num()], [1, 2, 3, 4], [{ a: 1 }, 'b']])
  }
  const KEYS = ['x', 'y', 'w', 'h', 'z', 'text', 'fill', 'stroke', 'from', 'to', 'points', 'locked', 'parent', 'rotation', 'id', 'type', '5', 'note']
  const props = (n) => {
    const out = {}
    for (let i = 0; i < n; i++) out[pick(KEYS)] = value()
    return out
  }
  const object = () => {
    const obj = { type: pick(TYPES), x: num(), y: num(), w: num(), h: num(), z: 'a' + int(30) }
    if (r() < 0.5) obj.text = pick(TEXTS)
    if (r() < 0.3) obj.fill = null
    if (r() < 0.3) obj.from = { id: pick(IDS) }
    if (r() < 0.2) obj.points = [0, 0, num(), num()]
    if (r() < 0.2) obj.id = 'ignored'
    if (r() < 0.1) obj['3'] = 'numeric key'
    return obj
  }
  const metaProps = () => {
    const out = {}
    const roll = r()
    if (roll < 0.12) out.locked = true
    else if (roll < 0.4) { out.locked = null; out.lockedBy = null }
    if (r() < 0.5) out.title = pick([...TEXTS, null])
    if (r() < 0.3) out.background = pick(['dots', 'grid', 'none', null])
    if (r() < 0.2) out.lockedBy = pick(['Olga', null])
    return out
  }
  const op = () => {
    const roll = r()
    if (roll < 0.3) return { k: 'create', id: pick(IDS), obj: object() }
    if (roll < 0.7) return { k: 'set', id: pick(IDS), props: props(1 + int(4)) }
    if (roll < 0.85) return { k: 'delete', id: pick(IDS) }
    return { k: 'meta', id: META, props: metaProps() }
  }
  const ops = (n) => Array.from({ length: n }, op)
  return { pick, int, ops, object }
}

const clone = (v) => JSON.parse(JSON.stringify(v))

// --- applyOps: a board and operations → the board and the inverse ---------
function opsCases(count) {
  const cases = []
  for (let seed = 1; seed <= count; seed++) {
    const r = rng(seed)
    const g = gen(r)
    let board = emptyBoard()
    board = applyOps(board, g.ops(10 + g.int(20))).board
    const ops = g.ops(1 + g.int(25))
    const { board: next, inverse } = applyOps(clone(board), clone(ops))
    cases.push({ seed, board: clone(board), ops, expected: { board: clone(next), inverse: clone(inverse) } })
  }
  return cases
}

// --- BoardServer: batches in order → seqs, resends, refusals, the log -----
function serverCase(seed, limits, keep, steps) {
  const r = rng(seed)
  const g = gen(r)
  const server = new BoardServer(undefined, limits, keep)
  const clients = ['tab-a', 'tab-b', 'tab-c']
  const cseq = Object.fromEntries(clients.map((c) => [c, 0]))
  const sent = Object.fromEntries(clients.map((c) => [c, []]))
  const lastSeq = Object.fromEntries(clients.map((c) => [c, 0]))
  const out = []
  for (let i = 0; i < steps; i++) {
    const client = g.pick(clients)
    let batch
    const resend = sent[client].length > 0 && r() < 0.12 && lastSeq[client] > server.snapshot().seq - keep
    if (resend) {
      // A resend of a batch sent before, or of a number the client skipped.
      batch = r() < 0.8 ? clone(g.pick(sent[client])) : { client, cseq: Math.max(0, cseq[client] - 1 - g.int(3)), ops: g.ops(1) }
    } else {
      cseq[client] += r() < 0.15 ? 2 + g.int(3) : 1
      const n = limits.maxOps < 50 && r() < 0.08 ? limits.maxOps + 1 + g.int(3) : g.int(Math.min(9, limits.maxOps + 1))
      batch = { client, cseq: cseq[client], ops: g.ops(n) }
      sent[client].push(clone(batch))
    }
    const step = { batch: clone(batch) }
    try {
      const { batch: ordered, fresh } = server.receive(clone(batch))
      step.expected = { seq: ordered.seq, fresh }
      if (fresh) {
        // On a locked board only the meta operations stay: their positions.
        if (ordered.ops.length !== batch.ops.length) step.expected.kept = batch.ops.flatMap((o, i) => (o.k === 'meta' ? [i] : []))
        if (JSON.stringify(ordered.ops) !== JSON.stringify(step.expected.kept ? step.expected.kept.map((i) => batch.ops[i]) : batch.ops)) throw new Error('unexpected ops')
        lastSeq[client] = ordered.seq
      }
    } catch (e) {
      step.expected = { error: String(e.message) }
    }
    out.push(step)
  }
  const snap = server.snapshot()
  const afters = [...new Set([0, 1, snap.seq - 1, snap.seq, snap.seq + 3, snap.seq - keep, snap.seq - keep - 1, Math.floor(snap.seq / 2), -1])]
  const since = afters.map((after) => {
    const batches = server.since(after)
    // The numbers alone: each batch's content is checked at its step.
    return { after, seqs: batches === null ? null : batches.map((b) => b.seq) }
  })
  return { seed, limits, keep, steps: out, expected: { snapshot: clone(snap), since } }
}

const servers = []
let seed = 1000
for (let i = 0; i < 16; i++) servers.push(serverCase(seed++, { maxOps: 500, maxObjects: 10000 }, 10000, 60))
for (let i = 0; i < 8; i++) servers.push(serverCase(seed++, { maxOps: 6, maxObjects: 9 }, 10000, 60))
for (let i = 0; i < 8; i++) servers.push(serverCase(seed++, { maxOps: 500, maxObjects: 10000 }, 12, 80))

const here = dirname(fileURLToPath(import.meta.url))
const file = join(here, 'reference.json')
writeFileSync(file, JSON.stringify({ applyOps: opsCases(200), server: servers }) + '\n')
console.log(`wrote ${file}`)
