import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';

export type ElementSize = { width: number; height: number };

const NO_SIZE: ElementSize = { width: 0, height: 0 };

// The size of an element in pixels, kept up to date while it resizes. 0 before the first measure.
export function useElementSize<T extends HTMLElement>(): [
    RefObject<T | null>,
    ElementSize,
] {
    const ref = useRef<T>(null);
    const [size, setSize] = useState<ElementSize>(NO_SIZE);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const read = () => {
            const box = element.getBoundingClientRect();
            setSize((current) =>
                current.width === box.width && current.height === box.height
                    ? current
                    : { width: box.width, height: box.height },
            );
        };

        read();
        const observer = new ResizeObserver(read);
        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    return [ref, size];
}
