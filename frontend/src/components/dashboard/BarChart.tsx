/** Minimal accessible bar chart (SVG + visually hidden data table). */
export function BarChart({ data, label }: { data: { date: string; count: number }[]; label: string }) {
  const max = Math.max(1, ...data.map((d) => d.count));
  const w = 600;
  const h = 120;
  const bw = w / Math.max(1, data.length);
  const total = data.reduce((s, d) => s + d.count, 0);
  return (
    <figure>
      <figcaption className="mb-1 text-sm font-medium">{label} <span className="text-muted">({total})</span></figcaption>
      <svg viewBox={`0 0 ${w} ${h}`} className="h-24 w-full" role="img" aria-label={`${label}: ${total} over ${data.length} days`} preserveAspectRatio="none">
        {data.map((d, i) => {
          const bh = (d.count / max) * (h - 4);
          return <rect key={d.date} x={i * bw + 1} y={h - bh} width={Math.max(1, bw - 2)} height={bh} className="fill-[var(--accent)]"><title>{`${d.date}: ${d.count}`}</title></rect>;
        })}
      </svg>
      <table className="sr-only">
        <caption>{label} per day</caption>
        <tbody>{data.map((d) => <tr key={d.date}><th scope="row">{d.date}</th><td>{d.count}</td></tr>)}</tbody>
      </table>
    </figure>
  );
}
