"""Tworzy obrazki testowe o różnych proporcjach: zrzut ekranu telefonu,
zdjęcie poziome i kwadrat. Ramka i przekątne pokazują, czy coś przycięto."""
import pathlib
from PIL import Image, ImageDraw

out = pathlib.Path(__file__).parent / 'fixtures'
out.mkdir(exist_ok=True)

for name, (w, h) in {'tall.png': (450, 975), 'wide.png': (900, 675), 'square.png': (700, 700)}.items():
    im = Image.new('RGB', (w, h), (235, 235, 235))
    d = ImageDraw.Draw(im)
    d.rectangle([0, 0, w - 1, h - 1], outline=(239, 22, 22), width=10)
    d.line([0, 0, w, h], fill=(17, 17, 17), width=6)
    d.line([w, 0, 0, h], fill=(17, 17, 17), width=6)
    im.save(out / name)
    print(f'  {name}: {w}x{h}')
