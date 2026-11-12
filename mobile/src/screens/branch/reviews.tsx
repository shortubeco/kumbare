/**
 * Şube ayrıntısı — Değerlendirmeler: özet ve dağılım, kendi değerlendirmem (yaz/düzenle),
 * yorum listesi, şikâyet. Puan 1–10 tam sayı; yalnızca bu şubede ödül kazanmış müşteri yazabilir.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';
import { Button, Card, Notice } from '@/components/ui';
import { api, ApiError } from '@/lib/api';
import { date } from '@/lib/format';
import { useBranchReviews } from '@/lib/queries';
import type { BranchDetail, Review } from '@/lib/types';
import { colors, font, radius, space } from '@/theme';

const SORTS = [
  { value: 'newest', label: 'En yeni' },
  { value: 'score', label: 'Puana göre' },
  { value: 'oldest', label: 'En eski' },
];

function Distribution({ b }: { b: BranchDetail }) {
  const rows = Array.from({ length: 10 }, (_, i) => 10 - i);
  const max = Math.max(1, ...rows.map((s) => b.rating_distribution[String(s)] ?? 0));
  return (
    <View style={{ gap: 4 }} accessibilityLabel="Puan dağılımı">
      {rows.map((s) => {
        const n = b.rating_distribution[String(s)] ?? 0;
        return (
          <View key={s} style={styles.distRow}>
            <Text style={styles.distScore}>{s}</Text>
            <View style={styles.distTrack}>
              <View style={[styles.distBar, { width: `${(n / max) * 100}%` }]} />
            </View>
            <Text style={styles.distCount}>{n}</Text>
          </View>
        );
      })}
    </View>
  );
}

function ReviewForm({ b }: { b: BranchDetail }) {
  const qc = useQueryClient();
  const mine = b.viewer?.my_review ?? null;
  const [editing, setEditing] = useState(!mine);
  const [score, setScore] = useState<number | null>(mine?.score ?? null);
  const [comment, setComment] = useState(mine?.comment ?? '');
  const [msg, setMsg] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);

  const save = useMutation({
    mutationFn: () =>
      api<{ message: string }>(`/branches/${b.id}/review`, { method: 'PUT', body: { score, comment: comment.trim() || null } }),
    onSuccess: (res) => {
      setMsg({ tone: 'success', text: res.message });
      setEditing(false);
      void qc.invalidateQueries({ queryKey: ['branch', b.id] });
    },
    onError: (e) => setMsg({ tone: 'error', text: e instanceof ApiError ? e.message : 'Kaydedilemedi.' }),
  });

  if (!b.viewer?.can_review && !mine) {
    return (
      <Card>
        <Text style={styles.muted}>Bu restoranda ödül kazandıktan sonra puan verip yorum yazabilirsiniz.</Text>
      </Card>
    );
  }

  if (mine && !editing) {
    return (
      <Card>
        <Text style={styles.h}>Değerlendirmeniz</Text>
        <Notice text={msg?.text} tone={msg?.tone} />
        <Text style={styles.score}>{mine.score_label}</Text>
        {mine.comment ? <Text style={styles.p}>{mine.comment}</Text> : null}
        {mine.status && mine.status !== 'published' ? <Text style={styles.muted}>Yorumunuz şu an yayında değil.</Text> : null}
        <Button title="Düzenle" variant="secondary" onPress={() => setEditing(true)} />
      </Card>
    );
  }

  return (
    <Card>
      <Text style={styles.h}>{mine ? 'Değerlendirmenizi düzenleyin' : 'Değerlendirme yazın'}</Text>
      <Notice text={msg?.text} tone={msg?.tone} />
      <Text style={styles.muted}>Puanınız (1 en düşük, 10 en yüksek)</Text>
      <View style={styles.scores} accessibilityRole="radiogroup">
        {Array.from({ length: 10 }, (_, i) => i + 1).map((n) => (
          <Pressable
            key={n}
            onPress={() => setScore(n)}
            style={[styles.scoreBtn, score === n && styles.scoreOn]}
            accessibilityRole="radio"
            accessibilityState={{ selected: score === n }}
            accessibilityLabel={`${n} puan`}
          >
            <Text style={[styles.scoreText, score === n && { color: colors.ink }]}>{n}</Text>
          </Pressable>
        ))}
      </View>
      <TextInput
        value={comment}
        onChangeText={setComment}
        placeholder="Yorumunuz (isteğe bağlı)"
        placeholderTextColor="#8a9592"
        multiline
        maxLength={2000}
        style={styles.textarea}
      />
      <View style={{ flexDirection: 'row', gap: space.sm }}>
        {mine ? <Button title="Vazgeç" variant="secondary" onPress={() => setEditing(false)} style={{ flex: 1 }} /> : null}
        <Button
          title="Kaydet"
          onPress={() => (score ? save.mutate() : setMsg({ tone: 'error', text: 'Lütfen bir puan seçin.' }))}
          loading={save.isPending}
          style={{ flex: 1.4 }}
        />
      </View>
    </Card>
  );
}

function ReportModal({ review, onClose }: { review: Review | null; onClose: () => void }) {
  const [reason, setReason] = useState('');
  const [msg, setMsg] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);
  const send = useMutation({
    mutationFn: () => api<{ message: string }>(`/reviews/${review!.id}/report`, { method: 'POST', body: { reason: reason.trim() } }),
    onSuccess: (res) => setMsg({ tone: 'success', text: res.message }),
    onError: (e) => setMsg({ tone: 'error', text: e instanceof ApiError ? e.message : 'Gönderilemedi.' }),
  });

  const close = () => {
    setReason('');
    setMsg(null);
    onClose();
  };

  return (
    <Modal visible={!!review} transparent animationType="fade" onRequestClose={close}>
      <View style={styles.backdrop}>
        <View style={styles.dialog}>
          <Text style={styles.h}>Yorumu şikâyet et</Text>
          <Text style={styles.muted}>Şikâyetiniz platform yöneticisi tarafından incelenir.</Text>
          <Notice text={msg?.text} tone={msg?.tone} />
          {msg?.tone === 'success' ? (
            <Button title="Kapat" onPress={close} />
          ) : (
            <>
              <TextInput
                value={reason}
                onChangeText={setReason}
                placeholder="Neden uygunsuz?"
                placeholderTextColor="#8a9592"
                multiline
                maxLength={1000}
                style={styles.textarea}
                autoFocus
              />
              <View style={{ flexDirection: 'row', gap: space.sm }}>
                <Button title="Vazgeç" variant="secondary" onPress={close} style={{ flex: 1 }} />
                <Button
                  title="Gönder"
                  onPress={() => (reason.trim() ? send.mutate() : setMsg({ tone: 'error', text: 'Lütfen nedenini yazın.' }))}
                  loading={send.isPending}
                  style={{ flex: 1 }}
                />
              </View>
            </>
          )}
        </View>
      </View>
    </Modal>
  );
}

export function BranchReviews({ b }: { b: BranchDetail }) {
  const [sort, setSort] = useState('newest');
  const [reporting, setReporting] = useState<Review | null>(null);
  const list = useBranchReviews(b.id, sort);
  const items = list.data?.pages.flatMap((p) => p.data) ?? [];

  return (
    <View style={{ gap: space.md }}>
      <Card>
        <Text style={styles.big}>{b.rating.count > 0 ? `${b.rating.average} / 10` : '–'}</Text>
        <Text style={styles.muted}>{b.rating.count > 0 ? `${b.rating.count} değerlendirme` : 'Henüz değerlendirme yok'}</Text>
        {b.rating.count > 0 ? <Distribution b={b} /> : null}
      </Card>

      <ReviewForm key={`${b.id}-${b.viewer?.my_review?.updated_at ?? 'yok'}`} b={b} />

      {b.rating.count > 0 ? (
        <View style={styles.sorts}>
          {SORTS.map((s) => (
            <Pressable
              key={s.value}
              onPress={() => setSort(s.value)}
              style={[styles.sort, sort === s.value && styles.sortOn]}
              accessibilityRole="button"
            >
              <Text style={[styles.sortText, sort === s.value && { color: colors.white }]}>{s.label}</Text>
            </Pressable>
          ))}
        </View>
      ) : null}

      {items.map((r) => (
        <Card key={r.id}>
          <View style={styles.rHead}>
            <Text style={styles.author}>{r.author ?? 'Müşteri'}</Text>
            <Text style={styles.rScore}>{r.score_label}</Text>
          </View>
          <Text style={styles.date}>{date(r.created_at)}</Text>
          {r.comment ? <Text style={styles.p}>{r.comment}</Text> : null}
          {r.reply ? (
            <View style={styles.reply}>
              <Text style={styles.replyTitle}>Restoranın cevabı</Text>
              <Text style={styles.p}>{r.reply.body}</Text>
            </View>
          ) : null}
          {r.id !== b.viewer?.my_review?.id ? (
            <Pressable onPress={() => setReporting(r)} hitSlop={6} accessibilityRole="button" style={{ alignSelf: 'flex-end' }}>
              <Text style={styles.report}>Şikâyet et</Text>
            </Pressable>
          ) : null}
        </Card>
      ))}

      {list.isLoading || list.isFetchingNextPage ? <ActivityIndicator color={colors.ink} /> : null}
      {list.hasNextPage && !list.isFetchingNextPage ? (
        <Button title="Daha fazla yorum" variant="secondary" onPress={() => void list.fetchNextPage()} />
      ) : null}

      <ReportModal review={reporting} onClose={() => setReporting(null)} />
    </View>
  );
}

const styles = StyleSheet.create({
  h: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  p: { fontSize: font.md, color: colors.ink, lineHeight: 22 },
  muted: { fontSize: font.sm, color: colors.gray, lineHeight: 20 },
  big: { fontSize: font.xxl, fontWeight: '800', color: colors.ink },
  score: { fontSize: font.lg, fontWeight: '800', color: colors.ink },
  distRow: { flexDirection: 'row', alignItems: 'center', gap: space.sm },
  distScore: { width: 20, textAlign: 'right', fontSize: font.xs + 1, color: colors.gray, fontVariant: ['tabular-nums'] },
  distTrack: { flex: 1, height: 8, backgroundColor: colors.line2, borderRadius: 4, overflow: 'hidden' },
  distBar: { height: 8, backgroundColor: colors.ink3, borderRadius: 4 },
  distCount: { width: 34, fontSize: font.xs + 1, color: colors.gray, fontVariant: ['tabular-nums'] },
  scores: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  scoreBtn: {
    width: 42,
    height: 42,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scoreOn: { backgroundColor: colors.gold, borderColor: colors.gold },
  scoreText: { fontSize: font.md, fontWeight: '700', color: colors.ink2 },
  textarea: {
    minHeight: 96,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    padding: space.md,
    fontSize: font.md,
    color: colors.ink,
    textAlignVertical: 'top',
  },
  sorts: { flexDirection: 'row', gap: space.sm },
  sort: {
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.white,
    borderRadius: radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  sortOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  sortText: { fontSize: font.sm, color: colors.ink, fontWeight: '600' },
  rHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  author: { fontSize: font.md, fontWeight: '700', color: colors.ink },
  rScore: { fontSize: font.md, fontWeight: '800', color: colors.ink },
  date: { fontSize: font.xs + 1, color: colors.gray },
  reply: {
    backgroundColor: colors.paper,
    borderLeftWidth: 3,
    borderLeftColor: colors.gold,
    borderRadius: radius.sm,
    padding: space.md,
    gap: 4,
  },
  replyTitle: { fontSize: font.sm, fontWeight: '700', color: colors.ink2 },
  report: { fontSize: font.xs + 1, color: colors.gray, textDecorationLine: 'underline' },
  backdrop: { flex: 1, backgroundColor: '#0008', alignItems: 'center', justifyContent: 'center', padding: space.lg },
  dialog: { width: '100%', maxWidth: 460, backgroundColor: colors.white, borderRadius: radius.lg, padding: space.lg, gap: space.md },
});
