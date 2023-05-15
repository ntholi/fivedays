import {
  Tabs,
  ActionIcon,
  Button,
  Container,
  Divider,
  Flex,
  Group,
  NumberInput,
  Stack,
  TextInput,
  Title,
  Table,
  Text,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import axios from 'axios';
import { classroom_v1 } from 'googleapis';
import React, { useState } from 'react';

type Props = {
  assessment: classroom_v1.Schema$CourseWork;
  setData: (data: Rubric[]) => void;
  data: Rubric[];
};

export default function RubricForm({ assessment, data, setData }: Props) {
  const form = useForm<Rubric>({
    initialValues: {
      questionId: assessment.id,
      title: '',
      description: '',
      points: 0,
    },
  });
  const [totalPoints, setTotalPoints] = useState<number | ''>(0);
  const [generating, setGenerating] = useState(false);

  async function generateRubric() {
    try {
      setGenerating(true);
      const response = await axios.post('/api/rubric/generate', {
        question: assessment.description,
        points: totalPoints,
      });
      setData(response.data);
    } catch (ex) {
      console.error(ex);
    } finally {
      setGenerating(false);
    }
  }

  function addItem(rubric: Rubric) {
    if (rubric.points > 0) {
      setData([rubric, ...data]);
      form.reset();
    }
  }

  return (
    <Tabs defaultValue='manual'>
      <Tabs.List>
        <Tabs.Tab value='manual' w={120}>
          Manual
        </Tabs.Tab>
        <Tabs.Tab value='assisted' w={120}>
          AI Assisted
        </Tabs.Tab>
      </Tabs.List>

      <Tabs.Panel value='manual' pt='xl'>
        <form onSubmit={form.onSubmit(addItem)}>
          <Stack spacing='sm'>
            <Group grow>
              <TextInput
                required
                label='Title'
                {...form.getInputProps('title')}
              />
              <NumberInput
                required
                label='Points'
                {...form.getInputProps('points')}
              />
            </Group>
            <TextInput
              label='Description'
              {...form.getInputProps('description')}
            />
            <Flex justify='flex-end'>
              <Button type='submit' variant='outline'>
                Add
              </Button>
            </Flex>
          </Stack>
        </form>
      </Tabs.Panel>

      <Tabs.Panel value='assisted' pt='xl'>
        <NumberInput
          label='Points'
          description='Total Number of Points'
          value={totalPoints}
          min={0}
          onChange={setTotalPoints}
          w={300}
        />
        <Button
          type='submit'
          onClick={generateRubric}
          variant='outline'
          mt='md'
          mb={40}
          w={300}
          disabled={!totalPoints}
          loading={generating}
        >
          Generate
        </Button>
      </Tabs.Panel>
    </Tabs>
  );
}
