import { Client, PrivateKey, TopicMessageSubmitTransaction } from "@hashgraph/sdk";
import "dotenv/config";

const message = process.argv[2];
const client = Client.forTestnet();
client.setOperator(
  process.env.HEDERA_ACCOUNT_ID,
  PrivateKey.fromStringECDSA(process.env.HEDERA_PRIVATE_KEY)
);

const resp = await new TopicMessageSubmitTransaction()
  .setTopicId(process.env.TOPIC_ID)
  .setMessage(message)
  .execute(client);
const receipt = await resp.getReceipt(client);

console.log(JSON.stringify({
  transactionId: resp.transactionId.toString(),
  sequence: receipt.topicSequenceNumber.toString(),
}));
process.exit(0);