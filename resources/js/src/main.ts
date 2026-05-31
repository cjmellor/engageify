import { startImpressionTracking } from "./tracker";

// The placeholders below are substituted with the consumer's config by the
// InjectImpressionScript middleware when the script is served.
startImpressionTracking({
    endpoint: "%_ENGAGEIFY_ENDPOINT_%",
    threshold: Number.parseFloat("%_ENGAGEIFY_THRESHOLD_%"),
    dwell: Number.parseInt("%_ENGAGEIFY_DWELL_%", 10),
});
