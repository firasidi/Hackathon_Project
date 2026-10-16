import { Client, PrivateKey, TopicMessageSubmitTransaction } from "@hashgraph/sdk";
import "dotenv/config";

const message = process.argv[2];
const network = process.env.HEDERA_NETWORK ?? "testnet";
const topicId = process.env.TOPIC_ID;
if (!topicId) throw new Error("TOPIC_ID is required");

const client = network === "mainnet"
  ? Client.forMainnet()
  : network === "testnet"
    ? Client.forTestnet()
    : (() => { throw new Error("Unsupported Hedera network"); })();
client.setOperator(
  process.env.HEDERA_ACCOUNT_ID,
  PrivateKey.fromStringECDSA(process.env.HEDERA_PRIVATE_KEY)
);

const resp = await new TopicMessageSubmitTransaction()
  .setTopicId(topicId)
  .setMessage(message)
  .execute(client);
const receipt = await resp.getReceipt(client);

console.log(JSON.stringify({
  transactionId: resp.transactionId.toString(),
  sequence: receipt.topicSequenceNumber.toString(),
  topicId,
  network,
}));
process.exit(0);