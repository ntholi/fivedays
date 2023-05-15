import { createRubricPrompt } from '@/lib/helpers/prompts/rubric';
import { NextApiRequest, NextApiResponse } from 'next';
import { Configuration, OpenAIApi } from 'openai';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse<Rubric[]>
) {
  const { question, points } = req.body;

  const configuration = new Configuration({
    organization: process.env.OPENAI_ORG_ID,
    apiKey: process.env.OPENAI_API_KEY,
  });
  const openai = new OpenAIApi(configuration);
  const response = await openai.createChatCompletion({
    model: 'gpt-3.5-turbo',
    messages: [
      {
        role: 'user',
        content: createRubricPrompt(question, points),
      },
    ],
  });

  console.log(response.data);

  res
    .status(200)
    .json(JSON.parse(response.data.choices[0].message?.content || ''));
}
