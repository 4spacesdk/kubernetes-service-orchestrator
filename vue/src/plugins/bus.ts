import mitt from 'mitt';
import type {Events} from "@/constants";
const emitter = mitt<Events>();
export default emitter;
