import type { AnalysisInput } from '@/lib/seo-analysis';
import type { Block } from '@/types';

let nextId = 0;

function block(
    type: string,
    attrs: Record<string, unknown> = {},
    content = '',
    children: Block[] = [],
): Block {
    nextId += 1;

    return { id: `b_fx${nextId}`, type, attrs, content, children };
}

export const paragraph = (html: string): Block =>
    block('paragraph', {}, `<p>${html}</p>`);
export const heading = (text: string, level = 2): Block =>
    block('heading', { level }, text);
export const list = (items: string[], ordered = false): Block =>
    block(
        'list',
        { ordered },
        `<${ordered ? 'ol' : 'ul'}>${items.map((item) => `<li><p>${item}</p></li>`).join('')}</${ordered ? 'ol' : 'ul'}>`,
    );
export const quote = (html: string, citation = ''): Block =>
    block('quote', { citation }, `<p>${html}</p>`);
export const image = (
    src: string,
    alt: string,
    mediaId: number | null = 12,
): Block => block('image', { mediaId, src, alt, caption: '' });
export const gallery = (images: { src: string; alt: string }[]): Block =>
    block('gallery', {
        mediaIds: images.map((_, index) => index + 40),
        images: images.map((item, index) => ({ ...item, mediaId: index + 40 })),
        columns: 3,
    });
export const button = (label: string, url: string): Block =>
    block('button', { label, url, style: 'primary' });
export const code = (source: string): Block =>
    block('code', { language: 'php' }, source);
export const html = (source: string): Block => block('html', {}, source);
export const table = (rows: string[][]): Block =>
    block('table', { header: true, rows });
export const columns = (...cells: Block[][]): Block =>
    block(
        'columns',
        {},
        '',
        cells.map((children) => block('column', {}, '', children)),
    );
export const section = (...children: Block[]): Block =>
    block('section', { tag: 'section' }, '', children);
export const element = (text: string, ...children: Block[]): Block =>
    block('element', { tag: 'aside' }, text, children);

export const TRAM_BLOCKS: Block[] = [
    paragraph(
        'Riding <strong>Tram 28</strong> is the classic way to see old Lisbon. The yellow cars climb steep hills and squeeze through narrow streets. First, you should know that the route runs from Martim Moniz to Campo de Ourique.',
    ),
    image(
        '/img/tram-28-alfama.jpg',
        'Yellow Tram 28 turning a corner in Alfama',
    ),
    heading('Where to catch Tram 28'),
    paragraph(
        'Most visitors start at Martim Moniz. However, the line there gets very long by ten in the morning. Instead, walk up to Graça and board at the second stop. You will often find a free seat. Also, the view from the back window is better on this side.',
    ),
    paragraph(
        'Buy a <a href="/guides/lisbon-transport-card">Viva Viagem card</a> before you ride. A single ticket on board costs more. For example, a day pass pays for itself after four trips. Then you can hop on and off all day.',
    ),
    heading('What you will see on the ride'),
    paragraph(
        'The tram rattles past the cathedral and the castle walls. Next, it drops into Baixa and climbs again to Chiado. In addition, you pass the Basilica da Estrela near the end. Keep your camera ready, because the best views come fast.',
    ),
    list([
        'Sé Cathedral, built in the twelfth century',
        'Miradouro de Santa Luzia, a terrace with river views',
        'Rua Augusta and the busy streets of Baixa',
        'The quiet gardens of Estrela',
    ]),
    paragraph(
        'Learn more about the history of the line on the <a href="https://www.carris.pt/en/" rel="noopener">official Carris site</a>. It has the timetable and news about works on the route.',
    ),
    heading('Tips for a calm ride'),
    paragraph(
        'Pickpockets love crowded trams. Therefore, keep your bag in front of you and your phone in a zipped pocket. Ride early or late in the day. In fact, the last hour before sunset gives soft light on the old houses.',
    ),
    paragraph(
        'Finally, give up your seat to older people who live in the area. For them, Tram 28 is not a ride for fun. It is how they get to the market and back home. A little care keeps the ride pleasant for everyone.',
    ),
    paragraph(
        'Want more ideas? Read our <a href="https://harborlane.test/guides/lisbon-in-three-days">three day Lisbon plan</a> and our guide to the best custard tarts in Belém. Both pair well with a morning on the tram.',
    ),
];

export const TRAM_INPUT: AnalysisInput = {
    title: "Riding Lisbon's Tram 28: a local's guide",
    seoTitle: "Riding Lisbon's Tram 28: a local's guide | Harbor Lane",
    description:
        'How to ride Tram 28 in Lisbon without the crowds: where to board, what you will see, and simple tips for a calm and safe trip across the old town.',
    slug: 'riding-tram-28',
    keyphrase: 'Tram 28',
    blocks: TRAM_BLOCKS,
    locale: 'en_US',
    siteHost: 'harborlane.test',
    hasFeaturedImage: true,
};

export const BREAD_BLOCKS: Block[] = [
    paragraph(
        'Hacer <strong>pan de masa madre</strong> en casa es más fácil de lo que parece. Solo necesitas harina, agua, sal y un poco de paciencia. Además, el sabor no se parece en nada al pan del supermercado.',
    ),
    heading('Qué es la masa madre'),
    paragraph(
        'La masa madre es una mezcla de harina y agua que fermenta sola. Las levaduras y bacterias del ambiente hacen el trabajo. Por eso el pan tiene ese sabor ácido tan rico. Sin embargo, al principio puede costar un poco.',
    ),
    image(
        '/img/masa-madre-tarro.jpg',
        'Tarro de cristal con masa madre activa',
    ),
    heading('Cómo hacer pan de masa madre paso a paso'),
    list(
        [
            'Mezcla 500 g de harina con 350 g de agua.',
            'Añade 100 g de masa madre activa y 10 g de sal.',
            'Haz pliegues cada media hora durante dos horas.',
            'Deja fermentar la masa en la nevera toda la noche.',
            'Hornea a 250 grados con vapor durante 40 minutos.',
        ],
        true,
    ),
    paragraph(
        '¿Cuánto tiempo hace falta? En total, unas 24 horas. Luego solo tienes que esperar a que el pan se enfríe. ¡No lo cortes caliente! La miga necesita reposar.',
    ),
    paragraph(
        'Si quieres saber más, lee nuestra <a href="/recetas/harinas-para-pan">guía de harinas para pan</a> o visita la web de la <a href="https://www.panaderos.example.org/">asociación de panaderos</a>.',
    ),
];

export const BREAD_INPUT: AnalysisInput = {
    title: 'Cómo hacer pan de masa madre en casa',
    seoTitle: 'Cómo hacer pan de masa madre en casa | Harbor Lane',
    description:
        'Aprende a hacer pan de masa madre en casa con harina, agua y sal. Una receta paso a paso, con tiempos, trucos y los errores más comunes que debes evitar.',
    slug: 'pan-de-masa-madre-en-casa',
    keyphrase: 'pan de masa madre',
    blocks: BREAD_BLOCKS,
    locale: 'es_ES',
    siteHost: 'harborlane.test',
    hasFeaturedImage: false,
};

// A long English post (about 3000 words) for the speed test.
export function longPost(paragraphs = 60): Block[] {
    const blocks: Block[] = [];

    for (let i = 0; i < paragraphs; i++) {
        if (i % 6 === 0) {
            blocks.push(heading(`Day ${i / 6 + 1} on the Lisbon trams`));
        }

        blocks.push(
            paragraph(
                `On stop ${i + 1}, the tram climbs past tiled houses and small cafés. However, the driver slows down near the market, because people cross the street without looking. Locals were seen waiting with bags of bread and fresh fish. Then the car turns into a narrow lane where you can almost touch the walls. For example, at Largo das Portas do Sol you can step off for a view of the river. Read the <a href="/guides/stop-${i}">stop guide</a> or the <a href="https://www.visitlisboa.com/">city guide</a> before you go. The ride is slow, noisy and full of charm.`,
            ),
        );
    }

    return blocks;
}
