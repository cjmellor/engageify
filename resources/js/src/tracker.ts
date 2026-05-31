interface ImpressionConfig {
    endpoint: string;
    threshold: number;
    dwell: number;
}

const ATTRIBUTE = "data-engageify-impression";

/**
 * Observe every element marked with the @impression directive and report a
 * single impression once it has dwelled in the viewport for long enough. A
 * fast scroll-past never reaches the dwell time, so glances don't count.
 */
export function startImpressionTracking(config: ImpressionConfig): IntersectionObserver {
    const counted = new WeakSet<Element>();
    const timers = new WeakMap<Element, ReturnType<typeof setTimeout>>();

    const report = (element: Element): void => {
        if (counted.has(element)) {
            return;
        }

        const token = element.getAttribute(ATTRIBUTE);

        if (token === null) {
            return;
        }

        counted.add(element);

        void fetch(config.endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ token }),
            keepalive: true,
        });
    };

    const observer = new IntersectionObserver(
        (entries: IntersectionObserverEntry[]): void => {
            entries.forEach((entry: IntersectionObserverEntry): void => {
                const element = entry.target;

                if (entry.isIntersecting && entry.intersectionRatio >= config.threshold) {
                    if (counted.has(element) || timers.has(element)) {
                        return;
                    }

                    timers.set(
                        element,
                        setTimeout((): void => {
                            timers.delete(element);
                            report(element);
                            observer.unobserve(element);
                        }, config.dwell),
                    );

                    return;
                }

                const timer = timers.get(element);

                if (timer !== undefined) {
                    clearTimeout(timer);
                    timers.delete(element);
                }
            });
        },
        { threshold: config.threshold },
    );

    document.querySelectorAll(`[${ATTRIBUTE}]`).forEach((element: Element): void => {
        observer.observe(element);
    });

    return observer;
}
