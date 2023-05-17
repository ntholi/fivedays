import axios from 'axios';
import type { NextApiRequest, NextApiResponse } from 'next';
import { getSession } from 'next-auth/react';
import { readFile } from './filereader';
import { createGradingPrompt } from '@/lib/helpers/prompts/grading';
import { getRubric } from '@/lib/services/rubricService';
import { createCompletion } from '@/lib/helpers/openai';

export default async function handler(
  req: NextApiRequest,
  res: NextApiResponse
) {
  const session = await getSession({ req });
  if (!session) {
    return res.status(401).json({ error: 'Unauthorized' });
  }

  const { fileId, questionId, question } = req.query;
  if (!fileId) {
    return res.status(400).json({ error: 'Missing "fileId" query parameter.' });
  }

  console.log('fileId', fileId);
  console.log('questionId', questionId);
  console.log('question', question);

  const rubrics = getRubric(String(questionId));

  const fileContent = await readFile(fileId, session);
  const prompt = createGradingPrompt(
    question as string,
    fileContent,
    JSON.stringify(rubrics)
  );

  const response = await createCompletion(prompt);
  res.status(200).json(JSON.parse(response.choices[0].message?.content || ''));
}
